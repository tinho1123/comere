<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Produtos';

    protected static ?string $modelLabel = 'Produto';

    protected static ?string $pluralModelLabel = 'Produtos';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Preço')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $set('total_amount', (float) $state - (float) $get('discounts'))),
                        Forms\Components\TextInput::make('discounts')
                            ->label('Descontos')
                            ->numeric()
                            ->default(0)
                            ->prefix('R$')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => $set('total_amount', (float) $get('amount') - (float) $state)),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Preço Final')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ]),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->default(1),
                        Forms\Components\Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                Forms\Components\Section::make('Configurações de Fiado')
                    ->schema([
                        Forms\Components\Toggle::make('is_for_favored')
                            ->label('Disponível para Fiado')
                            ->live(),
                        Forms\Components\TextInput::make('favored_price')
                            ->label('Preço no Fiado')
                            ->numeric()
                            ->prefix('R$')
                            ->visible(fn (Forms\Get $get): bool => $get('is_for_favored')),
                    ]),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->directory('products'),
                Forms\Components\Toggle::make('active')
                    ->label('Ativo')
                    ->required()
                    ->default(true),
                Forms\Components\Toggle::make('isCool')
                    ->label('Gelado?')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
