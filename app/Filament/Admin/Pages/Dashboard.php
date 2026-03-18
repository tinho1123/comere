<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\RecentOrdersWidget;
use App\Filament\Admin\Widgets\RecentTablesWidget;
use App\Filament\Admin\Widgets\RecentTransactionsWidget;
use App\Filament\Admin\Widgets\TransactionChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public function getWidgets(): array
    {
        return [
            TransactionChartWidget::class,
            RecentTransactionsWidget::class,
            RecentOrdersWidget::class,
            RecentTablesWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
