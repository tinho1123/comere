<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\CompanyTypeResource\Pages;
use App\Models\CompanyType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyTypeResource extends Resource
{
    protected static ?string $model = CompanyType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Tipos de Loja';

    protected static ?string $modelLabel = 'Tipo de Loja';

    protected static ?string $pluralModelLabel = 'Tipos de Loja';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100)
                ->placeholder('Ex: Restaurantes'),

            TextInput::make('icon')
                ->label('Ícone (emoji)')
                ->required()
                ->maxLength(10)
                ->placeholder('Ex: 🍔')
                ->helperText('Cole um emoji que represente este tipo de loja.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->width('40px'),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('companies_count')
                    ->label('Lojas')
                    ->counts('companies')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCompanyTypes::route('/'),
        ];
    }
}
