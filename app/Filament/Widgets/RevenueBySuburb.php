<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Revenue by suburb (base currency) — where the money is coming from. Limited to
 * the top suburbs so the chart stays readable.
 */
class RevenueBySuburb extends ChartWidget
{
    protected ?string $heading = 'Top suburbs by revenue (base currency)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'ZAR';

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
                'backgroundColor' => '#34d399',
                'borderColor' => '#059669',
            ]],
            'labels' => $rows->keys()->all(),
        ];
    }
}
