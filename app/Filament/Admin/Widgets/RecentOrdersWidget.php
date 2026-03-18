<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimos Pedidos';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        $company = filament()->getTenant();

        return $table
            ->query(
                Order::query()
                    ->with('client')
                    ->where('company_id', $company->id)
                    ->latest()
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->limit(20),

                TextColumn::make('notes')
                    ->label('Origem')
                    ->placeholder('—')
                    ->limit(20),

                TextColumn::make('status')
                    ->label('Status')
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

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL'),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m H:i'),
            ])
            ->headerActions([
                Action::make('ver_todos')
                    ->label('Ver todos')
                    ->url(fn () => OrderResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray'),
            ]);
    }
}
