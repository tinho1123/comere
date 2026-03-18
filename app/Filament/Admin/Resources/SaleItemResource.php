<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SaleItemResource\Pages;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleItemResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Transações';

    protected static ?string $modelLabel = 'Transação';

    protected static ?string $pluralModelLabel = 'Transações';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Vendas';
    }

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $company = filament()->getTenant();

                return $query
                    ->with(['order.client'])
                    ->whereHas('order', fn (Builder $q) => $q->where('company_id', $company->id));
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->summarize(
                        Sum::make()->money('BRL')->label('Total Geral')
                    ),

                TextColumn::make('order.client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('order.payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Order::paymentOptions()[$state] ?? $state) : '—')
                    ->placeholder('—'),

                TextColumn::make('order.status')
                    ->label('Pedido')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_PROCESSING => 'info',
                        Order::STATUS_SHIPPED => 'primary',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PROCESSING => 'Em separação',
                        Order::STATUS_SHIPPED => 'A caminho',
                        Order::STATUS_DELIVERED => 'Finalizado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                        default => $state ?? '—',
                    }),

                TextColumn::make('order.channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Order::CHANNEL_PRESENTIAL ? 'success' : 'info')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Order::CHANNEL_PRESENTIAL => 'Presencial',
                        Order::CHANNEL_ONLINE => 'Online',
                        default => '—',
                    }),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Pagamento')
                    ->options(Order::paymentOptions())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'])
                        ? $query
                        : $query->whereHas('order', fn (Builder $q) => $q->where('payment_method', $data['value']))
                    ),

                SelectFilter::make('status')
                    ->label('Status do Pedido')
                    ->options([
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PROCESSING => 'Em separação',
                        Order::STATUS_SHIPPED => 'A caminho',
                        Order::STATUS_DELIVERED => 'Finalizado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'])
                        ? $query
                        : $query->whereHas('order', fn (Builder $q) => $q->where('status', $data['value']))
                    ),

                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        Order::CHANNEL_ONLINE => 'Online',
                        Order::CHANNEL_PRESENTIAL => 'Presencial',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'])
                        ? $query
                        : $query->whereHas('order', fn (Builder $q) => $q->where('channel', $data['value']))
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleItems::route('/'),
        ];
    }
}
