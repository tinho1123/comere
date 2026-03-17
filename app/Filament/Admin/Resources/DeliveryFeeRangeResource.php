<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeliveryFeeRangeResource\Pages;
use App\Models\DeliveryFeeRange;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryFeeRangeResource extends Resource
{
    protected static ?string $model = DeliveryFeeRange::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

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

    public static function form(Form $form): Form
    {
        return $form->schema([
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

            View::make('filament.forms.components.delivery-range-map')
                ->columnSpanFull(),
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

                IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('max_km')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeliveryFeeRanges::route('/'),
        ];
    }
}
