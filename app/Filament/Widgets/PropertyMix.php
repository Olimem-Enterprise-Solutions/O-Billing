<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;

/**
 * The make-up of the ratepayer base: how many properties are residential,
 * business or government.
 */
class PropertyMix extends ChartWidget
{
    protected ?string $heading = 'Properties by type';

    protected static ?int $sort = 5;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Customer::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $labels = [
            'residential' => 'Residential',
            'business' => 'Business',
            'government' => 'Government',
        ];
        $colors = [
            'residential' => '#059669',
            'business' => '#6366f1',
            'government' => '#f59e0b',
        ];

        $data = [];
        $backgrounds = [];
        $chartLabels = [];
        foreach ($labels as $key => $label) {
            $chartLabels[] = $label;
            $data[] = (int) ($counts[$key] ?? 0);
            $backgrounds[] = $colors[$key];
        }

        return [
            'datasets' => [[
                'label' => 'Properties',
                'data' => $data,
                'backgroundColor' => $backgrounds,
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
            'labels' => $chartLabels,
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
