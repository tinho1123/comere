<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\SalesReportByPaymentMethod;
use App\Models\Order;
use App\Services\SalesReportService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentMethodSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $now = now();

        $data = app(SalesReportService::class)->report(
            $company,
            'month',
            (int) $now->format('n'),
            (int) $now->format('Y'),
        );

        $labels = Order::paymentOptions();

        return array_map(
            fn (string $method): Stat => Stat::make($labels[$method], 'R$ '.number_format($data['totals'][$method], 2, ',', '.'))
                ->description($data['counts'][$method].' '.($data['counts'][$method] === 1 ? 'venda' : 'vendas').' — ver detalhes em Gestão › Relatório de Vendas')
                ->color('gray'),
            SalesReportByPaymentMethod::METHODS,
        );
    }
}
