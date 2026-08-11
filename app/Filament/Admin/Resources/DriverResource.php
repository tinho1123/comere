<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DriverResource\Pages;
use App\Models\Driver;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Motoristas';

    protected static ?string $modelLabel = 'Motorista';

    protected static ?string $pluralModelLabel = 'Motoristas';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            Forms\Components\Select::make('vehicle_type')
                ->label('Tipo de veículo')
                ->options([
                    Driver::VEHICLE_MOTOBOY => 'Motoboy',
                    Driver::VEHICLE_CAR => 'Carro',
                ])
                ->native(false)
                ->required(),

            Forms\Components\TextInput::make('phone')
                ->label('Telefone')
                ->tel()
                ->maxLength(20)
                ->nullable(),

            Forms\Components\TextInput::make('cpf')
                ->label('CPF')
                ->maxLength(14)
                ->nullable(),

            Forms\Components\TextInput::make('delivery_fee')
                ->label('Valor por entrega')
                ->numeric()
                ->prefix('R$')
                ->minValue(0)
                ->step(0.01)
                ->required()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->where('company_id', Filament::getTenant()->id)
                ->withCount(['activeDeliveries'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('vehicle_type')
                    ->label('Veículo')
                    ->colors([
                        'warning' => Driver::VEHICLE_MOTOBOY,
                        'info' => Driver::VEHICLE_CAR,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Driver::VEHICLE_MOTOBOY => 'Motoboy',
                        Driver::VEHICLE_CAR => 'Carro',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Valor/entrega')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('availability')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Driver $record): string => $record->active_deliveries_count > 0 ? 'busy' : 'available')
                    ->color(fn (string $state): string => $state === 'busy' ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'busy' ? 'Ocupado' : 'Disponível'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Driver $record, Actions\DeleteAction $action) {
                        if ($record->deliveries()->exists()) {
                            $action->halt();
                            Notification::make()
                                ->danger()
                                ->title('Não é possível excluir')
                                ->body('Este motorista possui entregas registradas.')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
