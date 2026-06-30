<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceType;
use App\Models\Tariff;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Generates the invoices for a billing run: for every active customer, charge
 * each service they subscribe to using the tariff configured for their suburb,
 * in the customer's own currency. Tax is applied per the municipality's rate to
 * taxable services. Totals are accumulated per currency so a multi-currency
 * municipality can see exactly how much it is billing in each.
 *
 * Re-running a draft run is safe: existing invoices are cleared first.
 */
final class BillingRunService
{
    /**
     * Calculate and persist all invoices for the run, then mark it completed.
     *
     * @return array{invoice_count:int, currency_totals:array<string,float>}
     */
    public function generate(BillingRun $run): array
    {
        $municipality = $run->municipality;
        $taxRate = (float) $municipality->tax_rate;
        $period = $run->period_month;

        return DB::transaction(function () use ($run, $municipality, $taxRate, $period): array {
            // Clear any prior invoices for an idempotent re-run.
            $run->invoices()->each(fn (Invoice $i) => $i->delete());

            $customers = Customer::with(['services.serviceType', 'area'])
                ->where('active', true)
                ->get();

            // Pre-index active tariffs by area|service|currency for fast lookup.
            $tariffs = Tariff::where('active', true)->get()
                ->keyBy(fn (Tariff $t) => $this->tariffKey($t->area_id, $t->service_id, $t->currency));

            $currencyTotals = [];
            $sequence = 0;

            foreach ($customers as $customer) {
                $lines = [];
                $subtotal = Money::zero($customer->currency);
                $taxTotal = Money::zero($customer->currency);

                foreach ($customer->services as $service) {
                    $type = $service->serviceType;
                    if (! $service->active || $type === null || ! $type->active) {
                        continue;
                    }

                    $tariff = $tariffs->get($this->tariffKey($customer->area_id, $service->id, $customer->currency));
                    if ($tariff === null) {
                        continue; // No tariff for this suburb/service in the customer's currency.
                    }

                    [$quantity, $unitAmount, $amount] = $this->lineAmount($type, $tariff, $customer);
                    if ($amount->isZero()) {
                        continue;
                    }

                    $tax = $type->taxable
                        ? $amount->multipliedBy((string) $taxRate, RoundingMode::HALF_UP)
                        : Money::zero($customer->currency);

                    $subtotal = $subtotal->plus($amount);
                    $taxTotal = $taxTotal->plus($tax);

                    $lines[] = [
                        'service_id' => $service->id,
                        'tr_code' => $tariff->tr_code,
                        'description' => $service->displayName().' — '.$customer->area->name,
                        'quantity' => $quantity,
                        'unit_amount' => $unitAmount,
                        'amount' => $amount->getAmount()->toFloat(),
                        'tax_amount' => $tax->getAmount()->toFloat(),
                    ];
                }

                if ($lines === []) {
                    continue; // Nothing to bill this customer this period.
                }

                $total = $subtotal->plus($taxTotal);
                $sequence++;

                $invoice = $run->invoices()->create([
                    'municipality_id' => $municipality->id,
                    'customer_id' => $customer->id,
                    'invoice_number' => $this->invoiceNumber($municipality->code ?: (string) $municipality->id, $period, $sequence),
                    'period_month' => $period,
                    'currency' => $customer->currency,
                    'subtotal' => $subtotal->getAmount()->toFloat(),
                    'tax_total' => $taxTotal->getAmount()->toFloat(),
                    'total' => $total->getAmount()->toFloat(),
                    'status' => 'issued',
                    'issued_at' => now(),
                ]);

                $invoice->lines()->createMany($lines);

                $currencyTotals[$customer->currency] =
                    ($currencyTotals[$customer->currency] ?? 0) + $total->getAmount()->toFloat();
            }

            $run->forceFill([
                'status' => 'completed',
                'invoice_count' => $sequence,
                'currency_totals' => $currencyTotals,
                'run_at' => now(),
            ])->save();

            return ['invoice_count' => $sequence, 'currency_totals' => $currencyTotals];
        });
    }

    /**
     * @return array{0: float, 1: float, 2: Money} [quantity, unitAmount, lineAmount]
     */
    private function lineAmount(ServiceType $type, Tariff $tariff, Customer $customer): array
    {
        $rate = (string) $tariff->rate;

        return match ($type->billing_basis) {
            ServiceType::BASIS_PER_PROPERTY_VALUE => [
                1.0,
                (float) $rate,
                Money::of((string) ($customer->property_value ?? 0), $customer->currency)
                    ->multipliedBy($rate, RoundingMode::HALF_UP),
            ],
            // Metering deferred: per-unit currently bills a single unit.
            ServiceType::BASIS_PER_UNIT => [
                1.0,
                (float) $rate,
                Money::of($rate, $customer->currency, roundingMode: RoundingMode::HALF_UP),
            ],
            default => [
                1.0,
                (float) $rate,
                Money::of($rate, $customer->currency, roundingMode: RoundingMode::HALF_UP),
            ],
        };
    }

    private function tariffKey(int $areaId, int $serviceId, string $currency): string
    {
        return "{$areaId}|{$serviceId}|{$currency}";
    }

    private function invoiceNumber(string $prefix, \Illuminate\Support\Carbon $period, int $sequence): string
    {
        return sprintf('%s-%s-%05d', strtoupper($prefix), $period->format('Ym'), $sequence);
    }
}
