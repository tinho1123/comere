<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductSubcategoryResource\Pages;
use App\Models\ProductSubcategory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductSubcategoryResource extends Resource
{
    protected static ?string $model = ProductSubcategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Subcategorias';

    protected static ?string $modelLabel = 'Subcategoria';

    protected static ?string $pluralModelLabel = 'Subcategorias';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100)
                ->placeholder('Ex: 300ml, 500ml, Garrafa, Lata...'),

            Forms\Components\Toggle::make('active')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('company_id', Filament::getTenant()->id))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Produtos')
                    ->counts('products')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductSubcategories::route('/'),
            'create' => Pages\CreateProductSubcategory::route('/create'),
            'edit' => Pages\EditProductSubcategory::route('/{record}/edit'),
        ];
    }
}
