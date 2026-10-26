<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SupplierResource\Pages;
use App\Models\Supplier;
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

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Fornecedores';

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    public static function getNavigationGroup(): ?string
    {
        return 'Estoque';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255)
                ->extraAttributes(['data-tour' => 'fornecedor-nome']),

            Forms\Components\Select::make('visit_frequency')
                ->label('Frequência de visita')
                ->options([
                    Supplier::FREQUENCY_WEEKLY => 'Semanal',
                    Supplier::FREQUENCY_MONTHLY => 'Mensal',
                ])
                ->native(false)
                ->required()
                ->default(Supplier::FREQUENCY_WEEKLY),

            Forms\Components\TextInput::make('lead_time_days')
                ->label('Prazo de entrega (dias)')
                ->helperText('Dias entre o pedido com o time comercial e a chegada do caminhão.')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\DatePicker::make('last_visit_at')
                ->label('Última visita')
                ->native(false),

            Forms\Components\DatePicker::make('next_visit_at')
                ->label('Próxima visita')
                ->helperText('Calculada a partir da última visita + frequência, mas sempre editável (feriado, troca de rota ou de equipe).')
                ->native(false)
                ->dehydrateStateUsing(fn ($state) => $state)
                ->afterStateUpdated(fn ($set) => $set('next_visit_overridden', true))
                ->live(),

            Forms\Components\Hidden::make('next_visit_overridden')
                ->dehydrated(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_frequency')
                    ->label('Frequência')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Supplier::FREQUENCY_MONTHLY => 'Mensal',
                        default => 'Semanal',
                    }),

                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label('Prazo de entrega')
                    ->suffix(' dias')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label('Última visita')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_visit_at')
                    ->label('Próxima visita')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->description(fn (Supplier $record): ?string => $record->next_visit_overridden ? 'ajustada manualmente' : null),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Produtos')
                    ->counts('products')
                    ->suffix(' vinculados'),
            ])
            ->defaultSort('next_visit_at')
            ->recordActions([
                Actions\Action::make('mark_visited')
                    ->label('Marcar visita realizada')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Supplier $record): void {
                        $record->markVisited();

                        Notification::make()
                            ->success()
                            ->title('Visita registrada! Próxima visita recalculada.')
                            ->send();
                    }),

                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSuppliers::route('/'),
        ];
    }
}
