<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\ProductCategoryResource\Pages;
use App\Models\ProductsCategories;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductsCategories::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'master';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100)
                ->columnSpanFull(),

            TextInput::make('description')
                ->label('Descrição')
                ->maxLength(255)
                ->columnSpanFull(),

            Toggle::make('active')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->placeholder('—')
                    ->limit(50),

                TextColumn::make('products_count')
                    ->label('Produtos')
                    ->counts('products')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProductCategories::route('/'),
        ];
    }
}
