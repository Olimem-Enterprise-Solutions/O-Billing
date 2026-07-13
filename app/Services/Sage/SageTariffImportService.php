<?php

declare(strict_types=1);

namespace App\Services\Sage;

use App\Models\Municipality;
use App\Models\Service;
use App\Models\ServiceType;
use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Support\Facades\DB;

/**
 * Prices the recurring ratepayer services (Assessment Rates, Development Levy)
 * imported by {@see SageLedgerImportService} and attaches a tariff to every
 * affected property.
 *
 * The Sage database prices services in a per-item price list (USD Price List 4),
 * but it does NOT record which rate *variant* (density band / business category)
 * each stand falls into — that classification lives in the empty `_mtbl` property
 * register. We therefore apply one standard rate per account-type token, taken
 * from the price list, to every stand carrying that token. The token → variant
 * and frequency mapping below is the one deliberate assumption; adjust the RATE
 * CARD and re-run (it is idempotent) to change it.
 */
final class SageTariffImportService
{
    private const SAGE = 'sage';

    private const USD_PRICE_LIST = 4;

    /**
     * token => [Sage StkItem code used to price it, fallback USD rate, frequency, label].
     * Rates are read live from USD Price List 4; the fallback is used if absent.
     */
    private const RATE_CARD = [
        'ASSR' => ['P1SP4-SVS650', 90.0, 'annually', 'Assessment Rates'],
        'ASS' => ['P1SP4-SVS650', 90.0, 'annually', 'Assessment Rates'],
        'DEVC' => ['P1SP4-SVS304', 5.0, 'monthly', 'Development Levy'],
        'DEVR' => ['P1SP4-SVS304', 5.0, 'monthly', 'Development Levy'],
        'DEVM' => ['P1SP4-SVS304', 5.0, 'monthly', 'Development Levy'],
    ];

    private int $municipalityId;

    private array $counts = [];

    private array $warnings = [];

    /**
     * @return array{counts: array<string,int>, warnings: list<string>, municipality: string, lines: list<array<string,mixed>>}
     */
    public function run(): array
    {
        $muni = Municipality::firstOrNew(['code' => config('sage.municipality.code')]);
        if (! $muni->exists) {
            throw new \RuntimeException('Run sage:import-ledger first — the municipality does not exist yet.');
        }
        $this->municipalityId = $muni->id;

        return app(CurrentMunicipality::class)->runFor($this->municipalityId, function () use ($muni): array {
            $lines = [];
            DB::transaction(function () use (&$lines): void {
                $tariffs = 0;
                $priced = 0;
                foreach (self::RATE_CARD as $token => [$stkCode, $fallback, $frequency, $label]) {
                    $service = $this->serviceForToken($token);
                    if ($service === null) {
                        continue; // token not imported (no such ratepayers)
                    }

                    $rate = $this->priceFromSage($stkCode) ?? $fallback;
                    $sourced = $this->priceFromSage($stkCode) !== null;

                    // Record the service's natural cadence.
                    $service->serviceType->update(['default_frequency' => $frequency]);

                    // A tariff for every ward that has a stand billed this service,
                    // so the billing engine resolves a rate for each of them.
                    $areaIds = $this->areasBilling($service->id);
                    $this->replaceTariffs($service->id, $areaIds, $rate);

                    $tariffs += count($areaIds);
                    $priced++;
                    $lines[] = [
                        'token' => $token,
                        'service' => $label,
                        'rate' => $rate,
                        'currency' => 'USD',
                        'frequency' => $frequency,
                        'properties' => $this->propertyCount($service->id),
                        'wards' => count($areaIds),
                        'price_source' => $sourced ? 'USD Price List 4' : 'fallback',
                    ];
                }
                $this->counts = ['services_priced' => $priced, 'tariffs_created' => $tariffs];
            });

            $this->warnings[] = 'One standard rate was applied per account-type token (the Sage data does not record a per-property density/business category). Adjust the RATE CARD in SageTariffImportService and re-run to refine.';

            return [
                'counts' => $this->counts,
                'warnings' => $this->warnings,
                'municipality' => $muni->name,
                'lines' => $lines,
            ];
        });
    }

    /** The O-Billing service variant for a ledger account-type token, if imported. */
    private function serviceForToken(string $token): ?Service
    {
        $type = ServiceType::where('municipality_id', $this->municipalityId)
            ->where('code', "LEDGER-{$token}")->first();

        return $type?->services()->where('is_default', true)->first() ?? $type?->services()->first();
    }

    /** The current USD Price List 4 exclusive price for a Sage StkItem code. */
    private function priceFromSage(string $stkCode): ?float
    {
        $row = DB::connection(self::SAGE)->table('_etblPriceListPrices as p')
            ->join('StkItem as s', 's.StockLink', '=', 'p.iStockID')
            ->where('s.Code', $stkCode)
            ->where('p.iPriceListNameID', self::USD_PRICE_LIST)
            ->orderByDesc('p.fExclPrice')
            ->value('p.fExclPrice');

        return $row !== null ? (float) $row : null;
    }

    /** Distinct ward ids of the customers subscribed to a service. */
    private function areasBilling(int $serviceId): array
    {
        return DB::table('customer_service as cs')
            ->join('customers as c', 'c.id', '=', 'cs.customer_id')
            ->where('cs.service_id', $serviceId)
            ->distinct()->pluck('c.area_id')->all();
    }

    private function propertyCount(int $serviceId): int
    {
        return DB::table('customer_service')->where('service_id', $serviceId)->distinct()->count('customer_id');
    }

    /** @param list<int> $areaIds */
    private function replaceTariffs(int $serviceId, array $areaIds, float $rate): void
    {
        DB::table('tariffs')
            ->where('municipality_id', $this->municipalityId)
            ->where('service_id', $serviceId)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($areaIds as $areaId) {
            $rows[] = [
                'municipality_id' => $this->municipalityId,
                'area_id' => $areaId,
                'service_id' => $serviceId,
                'rate' => $rate,
                'currency' => 'USD',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('tariffs')->insert($chunk);
        }
    }
}
