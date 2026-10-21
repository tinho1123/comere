<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ProductMarginWidget extends BaseWidget
{
    protected static ?string $heading = 'Margem por produto';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $company = filament()->getTenant();

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.company_id', $company->id)
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('order_items.created_at', '>=', now()->subDays(30))
            ->selectRaw('MIN(order_items.id) as id')
            ->selectRaw('order_items.product_name as product_name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.total_amount) as gross')
            ->selectRaw('MAX(CASE WHEN products.cost IS NOT NULL AND products.cost > 0 THEN 1 ELSE 0 END) as has_cost')
            ->selectRaw('SUM(CASE WHEN products.cost IS NOT NULL AND products.cost > 0 THEN order_items.quantity * products.cost ELSE 0 END) as cost')
            ->groupBy('order_items.product_name')
            ->orderByDesc('gross')
            ->limit(5);

        return $table
            ->query($query)
            ->paginated(false)
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produto'),

                TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('gross')
                    ->label('Valor bruto')
                    ->money('BRL')
                    ->alignEnd(),

                TextColumn::make('cost')
                    ->label('Custo')
                    ->money('BRL')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $record->has_cost ? 'R$ '.number_format((float) $state, 2, ',', '.') : null)
                    ->alignEnd(),

                TextColumn::make('net')
                    ->label('Valor líquido')
                    ->state(fn ($record) => $record->has_cost ? (float) $record->gross - (float) $record->cost : null)
                    ->money('BRL')
                    ->placeholder('—')
                    ->alignEnd(),

                TextColumn::make('margin')
                    ->label('Margem')
                    ->state(function ($record) {
                        if (! $record->has_cost || (float) $record->gross <= 0) {
                            return null;
                        }

                        return (((float) $record->gross - (float) $record->cost) / (float) $record->gross) * 100;
                    })
                    ->formatStateUsing(fn (?float $state) => $state === null ? 'Sem custo' : number_format($state, 1, ',', '.').'%')
                    ->badge()
                    ->color(function (?float $state) {
                        if ($state === null) {
                            return 'gray';
                        }

                        return match (true) {
                            $state >= 45 => 'success',
                            $state >= 20 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->alignEnd(),
            ])
            ->emptyStateHeading('Nenhuma venda nos últimos 30 dias')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }
}
