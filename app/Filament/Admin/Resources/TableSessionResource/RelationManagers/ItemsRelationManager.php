<?php

namespace App\Filament\Admin\Resources\TableSessionResource\RelationManagers;

use App\Models\Product;
use App\Models\TableSessionItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens Consumidos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('product_id')
                ->label('Produto')
                ->options(function (): array {
                    $companyId = $this->getOwnerRecord()->company_id;

                    return Product::where('company_id', $companyId)
                        ->where('active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->name.' — R$ '.number_format($p->amount, 2, ',', '.')])
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $product = Product::find($state);
                    if ($product) {
                        $set('product_name', $product->name);
                        $set('unit_price', number_format($product->amount, 2, '.', ''));
                    }
                })
                ->columnSpanFull(),

            TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->required()
                ->columnSpan(1),

            TextInput::make('unit_price')
                ->label('Preço unitário')
                ->numeric()
                ->prefix('R$')
                ->required()
                ->columnSpan(1),

            TextInput::make('product_name')
                ->label('Nome no pedido')
                ->required()
                ->maxLength(200)
                ->helperText('Preenchido automaticamente ao selecionar o produto')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label('Produto'),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL'),

                TextColumn::make('total_amount')
                    ->label('Subtotal')
                    ->money('BRL'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar Item')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isOpen())
                    ->using(function (array $data): TableSessionItem {
                        $data['table_session_id'] = $this->getOwnerRecord()->id;
                        $data['total_amount'] = $data['quantity'] * $data['unit_price'];

                        return TableSessionItem::create($data);
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->label('Remover')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isOpen()),
            ]);
    }
}
