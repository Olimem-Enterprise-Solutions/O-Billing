<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Headline trend: how much was billed each month across all billing runs, in the
 * municipality's base currency. The filter drills the same months into amount,
 * invoice count, or average invoice size.
 */
class BillingIncomeByMonth extends ChartWidget
{
    protected ?string $heading = 'Billing income by month';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'billed';

    protected function getFilters(): ?array
    {
        return [
            'billed' => 'Amount billed',
            'invoices' => 'Invoices raised',
            'average' => 'Average invoice',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $base = Filament::getTenant()?->base_currency ?? 'USD';

        // period_month is normalised to the first of the month, so grouping on it
        // buckets by month directly (DB-agnostic — no date functions needed).
        $rows = Invoice::query()
            ->where('currency', $base)
            ->selectRaw('period_month, SUM(total) as total, COUNT(*) as cnt')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get();

        $labels = $rows->map(fn ($r) => Carbon::parse($r->period_month)->format('M Y'))->all();

        [$data, $label, $color] = match ($this->filter) {
            'invoices' => [
                $rows->map(fn ($r) => (int) $r->cnt)->all(),
                'Invoices raised',
                '#6366f1',
            ],
            'average' => [
                $rows->map(fn ($r) => $r->cnt > 0 ? round((float) $r->total / (int) $r->cnt, 2) : 0)->all(),
                'Average invoice ('.$base.')',
                '#f59e0b',
            ],
            default => [
                $rows->map(fn ($r) => round((float) $r->total, 2))->all(),
                'Amount billed ('.$base.')',
                '#059669',
            ],
        };

        return [
            'datasets' => [[
                'label' => $label,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $color.'22',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'pointBackgroundColor' => $color,
                'borderWidth' => 3,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(148,163,184,0.15)']],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
