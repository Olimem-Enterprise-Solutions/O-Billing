<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Where the money comes from: the top suburbs by revenue (base currency), as a
 * horizontal bar so the suburb names stay readable.
 */
class RevenueBySuburb extends ChartWidget
{
    protected ?string $heading = 'Top suburbs by revenue';

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'USD';

        $rows = Invoice::query()
            ->where('invoices.currency', $base)
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->join('areas', 'areas.id', '=', 'customers.area_id')
            ->selectRaw('areas.name as suburb, SUM(invoices.total) as total')
            ->groupBy('areas.name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'suburb');

        return [
            'datasets' => [[
                'label' => 'Billed ('.$base.')',
                'data' => $rows->map(fn ($t) => round((float) $t, 2))->values()->all(),
                'backgroundColor' => '#10b981',
                'borderColor' => '#059669',
                'borderRadius' => 6,
            ]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(148,163,184,0.15)']],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
