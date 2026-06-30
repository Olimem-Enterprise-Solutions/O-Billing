<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaType;
use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Tariff;
use App\Services\Billing\BillingRunService;
use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bills_customers_using_suburb_tariffs_and_taxes_only_taxable_services(): void
    {
        $municipality = Municipality::create([
            'name' => 'Test Muni',
            'code' => 'TST',
            'base_currency' => 'ZAR',
            'supported_currencies' => ['ZAR'],
            'tax_rate' => 0.15,
            'tax_label' => 'VAT',
        ]);

        app(CurrentMunicipality::class)->runFor($municipality->id, function () use ($municipality): void {
            $suburbType = AreaType::create([
                'municipality_id' => $municipality->id,
                'name' => 'Suburb', 'level' => 1, 'is_billing_level' => true,
            ]);

            $suburb = Area::create([
                'municipality_id' => $municipality->id,
                'area_type_id' => $suburbType->id, 'name' => 'Testville',
            ]);

            $rates = ServiceType::create([
                'municipality_id' => $municipality->id, 'name' => 'Property Rates', 'code' => 'RATES',
                'billing_basis' => ServiceType::BASIS_PER_PROPERTY_VALUE, 'taxable' => false, 'active' => true,
            ]);
            $refuse = ServiceType::create([
                'municipality_id' => $municipality->id, 'name' => 'Refuse', 'code' => 'REFUSE',
                'billing_basis' => ServiceType::BASIS_FLAT, 'taxable' => true, 'active' => true,
            ]);

            $ratesService = $rates->ensureDefaultService();
            $refuseService = $refuse->ensureDefaultService();

            Tariff::create(['municipality_id' => $municipality->id, 'area_id' => $suburb->id, 'service_id' => $ratesService->id, 'rate' => 0.0006, 'currency' => 'ZAR', 'active' => true]);
            Tariff::create(['municipality_id' => $municipality->id, 'area_id' => $suburb->id, 'service_id' => $refuseService->id, 'rate' => 250, 'currency' => 'ZAR', 'active' => true]);

            $customer = Customer::create([
                'municipality_id' => $municipality->id, 'area_id' => $suburb->id,
                'account_number' => 'A1', 'name' => 'Resident', 'type' => 'residential',
                'property_value' => 1_000_000, 'currency' => 'ZAR', 'active' => true,
            ]);
            $customer->services()->sync([$ratesService->id, $refuseService->id]);

            $run = BillingRun::create(['municipality_id' => $municipality->id, 'period_month' => now()->startOfMonth()]);
            $result = app(BillingRunService::class)->generate($run);

            $this->assertSame(1, $result['invoice_count']);

            $invoice = $run->invoices()->with('lines')->first();
            // Rates: 1,000,000 * 0.0006 = 600 (no tax). Refuse: 250 flat + 15% tax = 37.50.
            $this->assertCount(2, $invoice->lines);
            $this->assertSame(850.0, (float) $invoice->subtotal);
            $this->assertSame(37.5, (float) $invoice->tax_total);
            $this->assertSame(887.5, (float) $invoice->total);
            $this->assertSame(887.5, $result['currency_totals']['ZAR']);
        });
    }

    public function test_density_variants_of_a_service_type_bill_at_their_own_rate(): void
    {
        $municipality = Municipality::create([
            'name' => 'Variant Muni',
            'code' => 'VAR',
            'base_currency' => 'ZAR',
            'supported_currencies' => ['ZAR'],
            'tax_rate' => 0.0,
            'tax_label' => 'VAT',
        ]);

        app(CurrentMunicipality::class)->runFor($municipality->id, function () use ($municipality): void {
            $suburbType = AreaType::create([
                'municipality_id' => $municipality->id,
                'name' => 'Suburb', 'level' => 1, 'is_billing_level' => true,
            ]);
            $suburb = Area::create([
                'municipality_id' => $municipality->id,
                'area_type_id' => $suburbType->id, 'name' => 'Mixville',
            ]);

            // One service type, two density variants priced differently.
            $rates = ServiceType::create([
                'municipality_id' => $municipality->id, 'name' => 'Property Rates', 'code' => 'RATES',
                'billing_basis' => ServiceType::BASIS_PER_PROPERTY_VALUE, 'taxable' => false, 'active' => true,
            ]);
            $high = Service::create(['municipality_id' => $municipality->id, 'service_type_id' => $rates->id, 'name' => 'High Density', 'code' => 'RATES-HD', 'active' => true]);
            $low = Service::create(['municipality_id' => $municipality->id, 'service_type_id' => $rates->id, 'name' => 'Low Density', 'code' => 'RATES-LD', 'active' => true]);

            Tariff::create(['municipality_id' => $municipality->id, 'area_id' => $suburb->id, 'service_id' => $high->id, 'rate' => 0.0008, 'currency' => 'ZAR', 'active' => true]);
            Tariff::create(['municipality_id' => $municipality->id, 'area_id' => $suburb->id, 'service_id' => $low->id, 'rate' => 0.0005, 'currency' => 'ZAR', 'active' => true]);

            // Two customers, same suburb and property value, different density band.
            $hdCustomer = Customer::create([
                'municipality_id' => $municipality->id, 'area_id' => $suburb->id,
                'account_number' => 'HD1', 'name' => 'Township Resident', 'type' => 'residential',
                'property_value' => 1_000_000, 'currency' => 'ZAR', 'active' => true,
            ]);
            $hdCustomer->services()->sync([$high->id]);

            $ldCustomer = Customer::create([
                'municipality_id' => $municipality->id, 'area_id' => $suburb->id,
                'account_number' => 'LD1', 'name' => 'Suburb Resident', 'type' => 'residential',
                'property_value' => 1_000_000, 'currency' => 'ZAR', 'active' => true,
            ]);
            $ldCustomer->services()->sync([$low->id]);

            $run = BillingRun::create(['municipality_id' => $municipality->id, 'period_month' => now()->startOfMonth()]);
            app(BillingRunService::class)->generate($run);

            // High density: 1,000,000 * 0.0008 = 800. Low density: * 0.0005 = 500.
            $this->assertSame(800.0, (float) $hdCustomer->invoices()->first()->total);
            $this->assertSame(500.0, (float) $ldCustomer->invoices()->first()->total);

            // The variant shows in the line description.
            $line = $hdCustomer->invoices()->first()->lines()->first();
            $this->assertSame('Property Rates (High Density) — Mixville', $line->description);
        });
    }
}
