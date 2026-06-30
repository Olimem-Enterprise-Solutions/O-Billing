<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Area;
use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Currencies;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-line numbers for the municipality: who and what is being billed, and how
 * much has been billed in the base currency.
 */
class RevenueOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Overview';

    protected function getStats(): array
    {
        $municipality = Filament::getTenant();
        $base = $municipality?->base_currency ?? 'ZAR';

        $billedBase = (float) Invoice::where('currency', $base)->sum('total');

        return [
            Stat::make('Active properties', Customer::where('active', true)->count())
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Suburbs', Area::billingLevel()->count())
                ->icon('heroicon-o-map-pin')
                ->color('primary'),
            Stat::make('Billing runs', BillingRun::where('status', 'completed')->count())
                ->icon('heroicon-o-play-circle')
                ->color('success'),
            Stat::make('Total billed ('.$base.')', Currencies::format($billedBase, $base))
                ->description('All completed runs, base currency')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
