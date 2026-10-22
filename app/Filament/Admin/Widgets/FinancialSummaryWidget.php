<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $company = filament()->getTenant();

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.company_id', $company->id)
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->where('order_items.created_at', '>=', now()->subDays(30))
            ->selectRaw('SUM(order_items.total_amount) as gross')
            ->selectRaw('SUM(CASE WHEN COALESCE(order_items.cost, products.cost) > 0 THEN order_items.quantity * COALESCE(order_items.cost, products.cost) ELSE 0 END) as cost')
            ->selectRaw('SUM(CASE WHEN COALESCE(order_items.cost, products.cost) > 0 THEN order_items.total_amount ELSE 0 END) as gross_with_cost')
            ->first();

        $gross = (float) ($items->gross ?? 0);
        $cost = (float) ($items->cost ?? 0);
        $grossWithCost = (float) ($items->gross_with_cost ?? 0);
        $net = $gross - $cost;
        $marginPercent = $grossWithCost > 0 ? (($grossWithCost - $cost) / $grossWithCost) * 100 : null;

        return [
            Stat::make('Valor bruto', 'R$ '.number_format($gross, 2, ',', '.'))
                ->description('Vendas finalizadas nos últimos 30 dias (pedidos + mesas)')
                ->color('primary'),

            Stat::make('Custo dos produtos', 'R$ '.number_format($cost, 2, ',', '.'))
                ->description('Baseado no custo cadastrado de cada produto')
                ->color('danger'),

            Stat::make('Valor líquido', 'R$ '.number_format($net, 2, ',', '.'))
                ->description('Valor bruto − custo dos produtos')
                ->color('success'),

            Stat::make('Margem média', $marginPercent === null ? '—' : number_format($marginPercent, 1, ',', '.').'%')
                ->description($marginPercent === null ? 'Nenhum produto vendido tem custo cadastrado' : 'Entre os produtos com custo cadastrado')
                ->color('warning'),
        ];
    }
}
