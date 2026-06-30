<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\InvoiceLine;
use App\Models\ServiceType;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revenue (incl. tax) by service, in the municipality's base currency — shows
 * which services bring in the most money.
 */
class RevenueByService extends ChartWidget
{
    protected ?string $heading = 'Revenue by service group (base currency)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'ZAR';

        // Roll variants back up to their parent service type for the chart.
        $rows = InvoiceLine::query()
            ->join('services', 'services.id', '=', 'invoice_lines.service_id')
            ->whereHas('invoice', fn (Builder $q) => $q->where('currency', $base))
            ->selectRaw('services.service_type_id as service_type_id, SUM(invoice_lines.amount + invoice_lines.tax_amount) as total')
            ->groupBy('services.service_type_id')
            ->pluck('total', 'service_type_id');

        $names = ServiceType::whereIn('id', $rows->keys())->pluck('name', 'id');

        $labels = [];
        $data = [];
        foreach ($rows as $serviceId => $total) {
            $labels[] = $names[$serviceId] ?? 'Other';
            $data[] = round((float) $total, 2);
        }

        return [
            'datasets' => [[
                'label' => 'Billed ('.$base.')',
                'data' => $data,
                'backgroundColor' => '#059669',
                'borderColor' => '#047857',
            ]],
            'labels' => $labels,
        ];
    }
}
