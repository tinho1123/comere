<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryFeeRangeResource\Pages;
use App\Models\DeliveryFeeRange;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryFeeRangeResource extends Resource
{
    protected static ?string $model = DeliveryFeeRange::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Taxas de Entrega';

    protected static ?string $modelLabel = 'Faixa de Entrega';

    protected static ?string $pluralModelLabel = 'Taxas de Entrega';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Faixa de Entrega')
                ->schema([
                    Select::make('max_km')
                        ->label('Faixa (km)')
                        ->options(collect(DeliveryFeeRange::KM_RANGES)->mapWithKeys(fn ($km) => [$km => "Até {$km} km"]))
                        ->required()
                        ->live(),

                    TextInput::make('fee')
                        ->label('Taxa (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->minValue(0),

                    Toggle::make('is_active')
                        ->label('Ativa')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Mapa')
                ->schema([
                    View::make('filament.forms.components.delivery-range-map')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('max_km')
                    ->label('Faixa')
                    ->formatStateUsing(fn ($state) => "Até {$state} km")
                    ->sortable(),

                TextColumn::make('fee')
                    ->label('Taxa')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativa' : 'Inativa')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('max_km')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma faixa de entrega cadastrada')
            ->emptyStateDescription('Cadastre faixas de distância para calcular a taxa de entrega automaticamente.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeliveryFeeRanges::route('/'),
        ];
    }
}
