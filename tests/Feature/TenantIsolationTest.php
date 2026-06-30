<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaType;
use App\Models\Customer;
use App\Models\Municipality;
use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomerFor(Municipality $m, string $name): Customer
    {
        return app(CurrentMunicipality::class)->runFor($m->id, function () use ($m, $name): Customer {
            $type = AreaType::create(['municipality_id' => $m->id, 'name' => 'Suburb', 'level' => 1, 'is_billing_level' => true]);
            $area = Area::create(['municipality_id' => $m->id, 'area_type_id' => $type->id, 'name' => 'S']);

            return Customer::create([
                'municipality_id' => $m->id, 'area_id' => $area->id,
                'account_number' => $name, 'name' => $name, 'type' => 'residential',
                'currency' => 'ZAR', 'active' => true,
            ]);
        });
    }

    public function test_it_isolates_customer_data_between_municipalities(): void
    {
        $a = Municipality::create(['name' => 'A', 'base_currency' => 'ZAR']);
        $b = Municipality::create(['name' => 'B', 'base_currency' => 'ZAR']);

        $this->makeCustomerFor($a, 'cust-a');
        $this->makeCustomerFor($b, 'cust-b');

        app(CurrentMunicipality::class)->set($a->id);
        $this->assertSame(1, Customer::count());
        $this->assertSame('cust-a', Customer::first()->name);

        app(CurrentMunicipality::class)->set($b->id);
        $this->assertSame(1, Customer::count());
        $this->assertSame('cust-b', Customer::first()->name);

        $this->assertSame(2, Customer::query()->acrossAllMunicipalities()->count());
    }

    public function test_it_auto_stamps_municipality_id_on_create(): void
    {
        $a = Municipality::create(['name' => 'A', 'base_currency' => 'ZAR']);

        $customer = $this->makeCustomerFor($a, 'cust-a');

        $this->assertSame($a->id, $customer->municipality_id);
    }
}
