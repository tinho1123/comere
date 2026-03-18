<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SaleItemResource;
use App\Models\OrderItem;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentTransactionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimas Transações';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $company = filament()->getTenant();

        return $table
            ->query(
                OrderItem::query()
                    ->with('order')
                    ->whereHas('order', fn (Builder $q) => $q->where('company_id', $company->id))
                    ->latest()
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produto')
                    ->limit(25),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL'),

                TextColumn::make('order.payment_method')
                    ->label('Pgto')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Dinheiro',
                        'debit' => 'Débito',
                        'credit' => 'Crédito',
                        'pix' => 'PIX',
                        default => $state ?? '—',
                    })
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m H:i'),
            ])
            ->headerActions([
                Action::make('ver_todos')
                    ->label('Ver todas')
                    ->url(fn () => SaleItemResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray'),
            ]);
    }
}
