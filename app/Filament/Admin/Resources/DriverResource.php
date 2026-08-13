<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DriverResource\Pages;
use App\Models\Driver;
use App\Models\DriverCompany;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverResource extends Resource
{
    protected static ?string $model = DriverCompany::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Motoristas';

    protected static ?string $modelLabel = 'Vínculo de motorista';

    protected static ?string $pluralModelLabel = 'Motoristas';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id)
            ->with('driver');
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('delivery_fee')
                ->label('Valor por entrega')
                ->numeric()
                ->prefix('R$')
                ->minValue(0)
                ->step(0.01)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('driver.phone')
                    ->label('Telefone')
                    ->placeholder('—'),

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

                Tables\Columns\TextColumn::make('delivery_fee')
                    ->label('Valor/entrega')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Vínculo')
                    ->colors([
                        'warning' => Driver::LINK_PENDING,
                        'success' => Driver::LINK_ACCEPTED,
                        'danger' => Driver::LINK_REJECTED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Driver::LINK_PENDING => 'Convite pendente',
                        Driver::LINK_ACCEPTED => 'Aceito',
                        Driver::LINK_REJECTED => 'Recusado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Convidado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Driver::LINK_PENDING => 'Convite pendente',
                        Driver::LINK_ACCEPTED => 'Aceito',
                        Driver::LINK_REJECTED => 'Recusado',
                    ]),
            ])
            ->headerActions([
                Actions\Action::make('invite')
                    ->label('Convidar motorista')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone do motorista')
                            ->required()
                            ->helperText('O motorista precisa já ter uma conta criada em /drivers/cadastro com este telefone.'),

                        Forms\Components\TextInput::make('delivery_fee')
                            ->label('Valor proposto por entrega')
                            ->numeric()
                            ->prefix('R$')
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->default(0),
                    ])
                    ->action(function (array $data): void {
                        $companyId = Filament::getTenant()->id;
                        $driver = Driver::where('phone', $data['phone'])->first();

                        if (! $driver) {
                            Notification::make()
                                ->danger()
                                ->title('Motorista não encontrado')
                                ->body('Nenhum motorista cadastrado com esse telefone. Peça para ele se cadastrar em /drivers/cadastro primeiro.')
                                ->send();

                            return;
                        }

                        $existing = DriverCompany::where('driver_id', $driver->id)
                            ->where('company_id', $companyId)
                            ->first();

                        if ($existing && $existing->status !== Driver::LINK_REJECTED) {
                            Notification::make()
                                ->warning()
                                ->title('Motorista já vinculado')
                                ->body('Já existe um convite pendente ou aceito para este motorista.')
                                ->send();

                            return;
                        }

                        if ($existing) {
                            $existing->update([
                                'status' => Driver::LINK_PENDING,
                                'delivery_fee' => $data['delivery_fee'],
                                'responded_at' => null,
                            ]);
                        } else {
                            DriverCompany::create([
                                'driver_id' => $driver->id,
                                'company_id' => $companyId,
                                'status' => Driver::LINK_PENDING,
                                'delivery_fee' => $data['delivery_fee'],
                            ]);
                        }

                        Notification::make()
                            ->success()
                            ->title('Convite enviado!')
                            ->body("{$driver->name} vai ver o convite no painel dele.")
                            ->send();
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('Editar taxa'),

                Actions\Action::make('cancel_invite')
                    ->label('Cancelar convite')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DriverCompany $record): bool => $record->status === Driver::LINK_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (DriverCompany $record) => $record->delete()),

                Actions\DeleteAction::make()
                    ->label('Remover vínculo')
                    ->visible(fn (DriverCompany $record): bool => $record->status !== Driver::LINK_PENDING),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDrivers::route('/'),
        ];
    }
}
