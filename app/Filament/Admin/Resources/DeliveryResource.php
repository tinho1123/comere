<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryResource\Pages;
use App\Models\Delivery;
use App\Models\Driver;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Entregas';

    protected static ?string $modelLabel = 'Entrega';

    protected static ?string $pluralModelLabel = 'Entregas';

    public static function getNavigationGroup(): ?string
    {
        return 'Vendas';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('notes')
                ->label('Observações'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;

        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->where('deliveries.company_id', $companyId)
                ->with(['driver', 'order'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.uuid')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state) => '#'.strtoupper(substr($state, 0, 8)))
                    ->searchable(),

                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Motorista')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('driver.vehicle_type')
                    ->label('Veículo')
                    ->colors([
                        'warning' => Driver::VEHICLE_MOTOBOY,
                        'info' => Driver::VEHICLE_CAR,
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        Driver::VEHICLE_MOTOBOY => 'Motoboy',
                        Driver::VEHICLE_CAR => 'Carro',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => Delivery::STATUS_DISPATCHED,
                        'success' => Delivery::STATUS_DELIVERED,
                        'danger' => Delivery::STATUS_FAILED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Delivery::STATUS_DISPATCHED => 'Em rota',
                        Delivery::STATUS_DELIVERED => 'Entregue',
                        Delivery::STATUS_FAILED => 'Falhou',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('driver_fee')
                    ->label('Valor entrega')
                    ->money('BRL'),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Pago')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('dispatched_at')
                    ->label('Despachado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Entregue em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('dispatched_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Delivery::STATUS_DISPATCHED => 'Em rota',
                        Delivery::STATUS_DELIVERED => 'Entregue',
                        Delivery::STATUS_FAILED => 'Falhou',
                    ]),

                Tables\Filters\SelectFilter::make('driver_id')
                    ->label('Motorista')
                    ->options(
                        Driver::where('company_id', $companyId)
                            ->where('is_active', true)
                            ->pluck('name', 'id')
                    ),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Pagamento')
                    ->trueLabel('Pago')
                    ->falseLabel('Pendente'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Marcar entregue')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Delivery $record): bool => $record->status === Delivery::STATUS_DISPATCHED)
                    ->requiresConfirmation()
                    ->action(function (Delivery $record): void {
                        $record->update([
                            'status' => Delivery::STATUS_DELIVERED,
                            'delivered_at' => now(),
                        ]);
                        $record->order->deliver();

                        Notification::make()
                            ->success()
                            ->title('Entrega concluída!')
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_failed')
                    ->label('Falhou')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Delivery $record): bool => $record->status === Delivery::STATUS_DISPATCHED)
                    ->requiresConfirmation()
                    ->action(function (Delivery $record): void {
                        $record->update(['status' => Delivery::STATUS_FAILED]);

                        Notification::make()
                            ->warning()
                            ->title('Entrega marcada como falha.')
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Marcar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (Delivery $record): bool => $record->status === Delivery::STATUS_DELIVERED && ! $record->is_paid)
                    ->requiresConfirmation()
                    ->action(fn (Delivery $record) => $record->update(['is_paid' => true])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_mark_paid')
                        ->label('Marcar como pago')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_paid' => true])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveries::route('/'),
        ];
    }
}
