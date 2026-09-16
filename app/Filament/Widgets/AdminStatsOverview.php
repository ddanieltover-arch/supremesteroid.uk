<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Reporting\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $metricsService = app(AdminMetricsService::class);
        $metrics = $metricsService->getMetrics();

        return [
            Stat::make('Orders today', (string) $metrics['orders_today'])
                ->description('Placed since midnight')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Pending payments', (string) $metrics['pending_payments'])
                ->description('Awaiting transfer or transaction reference')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Awaiting fulfilment', (string) $metrics['paid_awaiting_fulfillment'])
                ->description('Paid or processing, not yet shipped')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),

            Stat::make('Low stock', (string) $metrics['low_stock_count'])
                ->description('Items at or below safety stock')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Paid revenue', '£' . $metrics['revenue_paid_amount'])
                ->description('Sum of verified payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Payments under review', (string) $metrics['payments_under_review'])
                ->description('Submissions ready for staff approval')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),
        ];
    }
}
