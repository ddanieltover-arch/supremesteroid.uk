<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Reporting\AdminMetricsService;
use Filament\Widgets\ChartWidget;

class RevenueOverTimeChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Verified revenue (14 days)';

    protected ?string $description = 'Paid payments by paid date (£)';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = app(AdminMetricsService::class)->revenuePerDay(14);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (£)',
                    'data' => $series['data'],
                    'backgroundColor' => 'rgba(15, 118, 110, 0.75)',
                    'borderColor' => '#0f766e',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
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
                ],
            ],
        ];
    }
}
