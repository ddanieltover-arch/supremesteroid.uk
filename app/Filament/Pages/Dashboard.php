<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\OrdersOverTimeChart;
use App\Filament\Widgets\PaymentApprovalQueueWidget;
use App\Filament\Widgets\RevenueOverTimeChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            AdminStatsOverview::class,
            OrdersOverTimeChart::class,
            RevenueOverTimeChart::class,
            PaymentApprovalQueueWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
