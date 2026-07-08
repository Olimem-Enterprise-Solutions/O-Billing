<?php

declare(strict_types=1);

namespace App\Services\Sage;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new property (and, if needed, its owner debtor) in Sage so a
 * property added in O-Billing shows up in the Sage property list.
 *
 * A Sage property (`_ccg_EB_Properties`) needs only an ErfNumber; its owner is a
 * Sage debtor (`Client`). Debtor control in Sage Evolution is a company-level GL
 * account, not per-client, and neither table has triggers or required columns, so
 * a new debtor is inert (no ledger impact) until it is actually billed. New
 * debtors copy an existing debtor's class / currency / terms so they behave the
 * same as the rest.
 *
 * All writes go to the `sage_write` connection (the NON-PRODUCTION test company by
 * default), and the property/owner links are resolved against that same target.
 */
final class SagePropertyWriter
{
    private const CONN = 'sage_write';

    /**
     * @return array{
     *   ok: bool, database: string, error?: string,
     *   property_id?: int, owner_dclink?: int, owner_created?: bool,
     *   area_linked?: bool, services?: int, erf?: string
     * }
     */
    public function pushProperty(Customer $customer): array
    {
        $database = (string) config('database.connections.'.self::CONN.'.database');
        $erf = trim((string) $customer->account_number);

        if ($erf === '') {
            return ['ok' => false, 'database' => $database, 'error' => 'The property needs an account/erf number before it can be sent to Sage.'];
        }

        // Don't create a duplicate erf.
        if (DB::connection(self::CONN)->table('_ccg_EB_Properties')->where('ErfNumber', $erf)->exists()) {
            return ['ok' => false, 'database' => $database,
                'error' => "A property with erf {$erf} already exists in {$database}."];
        }

        $sageAreaId = $this->resolveAreaId($customer);
        $municipalityId = DB::connection(self::CONN)->table('_ccg_EB_Municipalities')->min('ID');

        $result = DB::connection(self::CONN)->transaction(function () use ($customer, $erf, $sageAreaId, $municipalityId): array {
            [$ownerDclink, $ownerCreated] = $this->resolveOwner($customer, $erf);

            $propertyId = (int) DB::connection(self::CONN)->table('_ccg_EB_Properties')->insertGetId([
                'ErfNumber' => $erf,
                'OwnerID' => $ownerDclink,
                'AreaID' => $sageAreaId,
                'MunicipalityID' => $municipalityId,
                'MarketValue' => (float) ($customer->property_value ?? 0),
                'LandValue' => (float) ($customer->land_value ?? 0),
                'ImprovementValue' => (float) ($customer->improvement_value ?? 0),
                'LandSize' => (float) ($customer->land_size ?? 0),
                'AddressLine1' => $customer->address,
                'RegisteredOwnerName' => $customer->name,
                'SubDivided' => false,
                'Households' => 1,
                'UserCreated' => 'O-Billing',
                'DateCreated' => now(),
            ], 'ID');

            $services = $this->createPropertyServices($customer, $propertyId, $ownerDclink);

            return [$propertyId, $ownerDclink, $ownerCreated, $services];
        });

        [$propertyId, $ownerDclink, $ownerCreated, $services] = $result;

        return [
            'ok' => true,
            'database' => $database,
            'property_id' => (int) $propertyId,
            'owner_dclink' => (int) $ownerDclink,
            'owner_created' => $ownerCreated,
            'area_linked' => $sageAreaId !== null,
            'services' => $services,
            'erf' => $erf,
        ];
    }

    /** Link to an existing Sage debtor with the same account, or create one. */
    private function resolveOwner(Customer $customer, string $account): array
    {
        $existing = DB::connection(self::CONN)->table('Client')->where('Account', $account)->value('DCLink');
        if ($existing !== null) {
            return [(int) $existing, false];
        }

        // Copy an existing debtor's classification so the new one is consistent.
        $template = DB::connection(self::CONN)->table('Client')
            ->whereNotNull('iClassID')
            ->first(['iClassID', 'iSettlementTermsID', 'iAgeingTermID', 'iAreasID', 'AccountTerms', 'iARPriceListNameID']);

        $currencyId = DB::connection(self::CONN)->table('Currency')
            ->where('CurrencyCode', $customer->currency)->value('CurrencyLink') ?? 1;

        $dclink = DB::connection(self::CONN)->table('Client')->insertGetId([
            'Account' => $account,
            'Name' => $customer->name,
            'Physical1' => $customer->address,
            'Telephone' => $customer->phone,
            'EMail' => $customer->email,
            'iCurrencyID' => (int) $currencyId,
            'iClassID' => $template->iClassID ?? null,
            'iSettlementTermsID' => $template->iSettlementTermsID ?? 0,
            'iAgeingTermID' => $template->iAgeingTermID ?? 1,
            'iAreasID' => $template->iAreasID ?? null,
            'AccountTerms' => $template->AccountTerms ?? 0,
            'iARPriceListNameID' => $template->iARPriceListNameID ?? null,
            'UseEmail' => (bool) $customer->email,
        ], 'DCLink');

        return [(int) $dclink, true];
    }

    /**
     * Make the new property billable: create a `_ccg_EB_PropertyServices` link for
     * each service the O-Billing property subscribes to (mapped back to its Sage
     * tariff + service). Returns how many were created.
     */
    private function createPropertyServices(Customer $customer, int $propertyId, int $ownerDclink): int
    {
        $now = now();
        $count = 0;

        foreach ($customer->services as $service) {
            $tariffId = $this->tariffId($service->code);
            if ($tariffId === null) {
                continue;
            }
            $sageServiceId = DB::connection(self::CONN)->table('_ccg_EB_Tariffs')->where('ID', $tariffId)->value('ServiceID');
            if ($sageServiceId === null) {
                continue; // tariff not present in the target database
            }

            DB::connection(self::CONN)->table('_ccg_EB_PropertyServices')->insert([
                'PropertyID' => $propertyId,
                'CustomerID' => $ownerDclink,
                'ServiceID' => (int) $sageServiceId,
                'TariffID' => $tariffId,
                'Billable' => true,
                'UserCreated' => 'O-Billing',
                'DateCreated' => $now,
            ]);
            $count++;
        }

        return $count;
    }

    /** Recover the Sage tariff id from an imported service's code ("TRF-123" → 123). */
    private function tariffId(?string $code): ?int
    {
        return ($code !== null && str_starts_with($code, 'TRF-')) ? (int) substr($code, 4) : null;
    }

    /** The Sage area id behind an imported O-Billing area ("area:{id}" → {id}). */
    private function resolveAreaId(Customer $customer): ?int
    {
        $sageId = $customer->area?->sage_id;

        return ($sageId !== null && str_starts_with($sageId, 'area:'))
            ? (int) substr($sageId, 5)
            : null;
    }
}
