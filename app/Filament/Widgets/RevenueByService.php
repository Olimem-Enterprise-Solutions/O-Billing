<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ServiceType;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Revenue (incl. tax) composition by service group, in the base currency. The
 * filter drills down from all-time to a single billing month, so you can see
 * what each month's income was made up of.
 */
class RevenueByService extends ChartWidget
{
    protected ?string $heading = 'Revenue by service';

    protected static ?int $sort = 3;

    public ?string $filter = 'all';

    /** A readable, modern categorical palette. */
    private const PALETTE = [
        '#059669', '#6366f1', '#f59e0b', '#ef4444', '#14b8a6', '#8b5cf6',
        '#ec4899', '#3b82f6', '#84cc16', '#f97316', '#06b6d4', '#a855f7',
    ];

    protected function getFilters(): ?array
    {
        $base = Filament::getTenant()?->base_currency ?? 'USD';

        $options = ['all' => 'All time'];
        foreach (Invoice::where('currency', $base)->distinct()->orderByDesc('period_month')->pluck('period_month') as $month) {
            $options[Carbon::parse($month)->format('Y-m')] = Carbon::parse($month)->format('M Y');
        }

        return $options;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'USD';

        $query = InvoiceLine::query()
            ->join('services', 'services.id', '=', 'invoice_lines.service_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.currency', $base);

        if ($this->filter && $this->filter !== 'all') {
            $query->whereDate('invoices.period_month', $this->filter.'-01');
        }

        // Roll service variants back up to their parent service group.
        $rows = $query
            ->selectRaw('services.service_type_id as stid, SUM(invoice_lines.amount + invoice_lines.tax_amount) as total')
            ->groupBy('services.service_type_id')
            ->orderByDesc('total')
            ->pluck('total', 'stid');

        $names = ServiceType::whereIn('id', $rows->keys())->pluck('name', 'id');

        $labels = [];
        $data = [];
        foreach ($rows as $serviceTypeId => $total) {
            $labels[] = $names[$serviceTypeId] ?? 'Other';
            $data[] = round((float) $total, 2);
        }

        return [
            'datasets' => [[
                'label' => 'Billed ('.$base.')',
                'data' => $data,
                'backgroundColor' => array_slice(self::PALETTE, 0, max(1, count($data))),
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right', 'labels' => ['boxWidth' => 12, 'padding' => 12]],
            ],
            'cutout' => '55%',
        ];
    }
}
