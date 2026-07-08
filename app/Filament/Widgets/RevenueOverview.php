<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Currencies;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Top-line numbers for the municipality: who and what is being billed, how much
 * has been billed in the base currency, and the latest month's movement — with
 * sparklines of the monthly trend.
 */
class RevenueOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Overview';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'USD';

        $monthly = Invoice::query()
            ->where('currency', $base)
            ->selectRaw('period_month, SUM(total) as total')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->pluck('total', 'period_month');

        $spark = $monthly->map(fn ($t) => round((float) $t, 2))->values()->all();
        $billedBase = array_sum($spark);
        $latest = $spark === [] ? 0.0 : (float) end($spark);
        $previous = count($spark) >= 2 ? (float) $spark[count($spark) - 2] : 0.0;
        $trend = $previous > 0 ? (($latest - $previous) / $previous) * 100 : 0.0;
        $latestMonth = $monthly->keys()->last();
        $latestLabel = $latestMonth ? Carbon::parse($latestMonth)->format('M Y') : '—';

        return [
            Stat::make('Active properties', number_format(Customer::where('active', true)->count()))
                ->description('Ratepayer accounts')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('primary'),

            Stat::make('Invoices raised', number_format(Invoice::count()))
                ->description(BillingRun::where('status', 'completed')->count().' completed runs')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('primary'),

            Stat::make('Total billed ('.$base.')', Currencies::compact($billedBase, $base))
                ->description(Currencies::format($billedBase, $base).' · all runs')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($spark ?: [0]),

            Stat::make('Latest month billed', Currencies::compact($latest, $base))
                ->description($latestLabel.' · '.($trend >= 0 ? '+' : '').number_format($trend, 1).'% vs prev')
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'danger')
                ->chart($spark ?: [0]),
        ];
    }
}
