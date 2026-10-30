<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Reporting\AdminMetricsService;
use Filament\Widgets\ChartWidget;

class OrdersOverTimeChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Orders (14 days)';

    protected ?string $description = 'Daily order volume';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = app(AdminMetricsService::class)->ordersPerDay(14);

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $series['data'],
                    'borderColor' => '#d97706',
                    'backgroundColor' => 'rgba(217, 119, 6, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
