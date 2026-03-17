<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    public static function getNavigationGroup(): ?string
    {
        return 'Vendas';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Order::STATUS_PENDING)->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cliente')
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->options(function () {
                            $company = filament()->getTenant();

                            return Client::whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name.' — '.$c->document_number]);
                        })
                        ->searchable()
                        ->required()
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('client.name')
                        ->label('Cliente')
                        ->disabled()
                        ->visibleOn('edit'),
                ]),

            Forms\Components\Section::make('Produtos')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Produto')
                                ->options(function () {
                                    $company = filament()->getTenant();

                                    return Product::where('company_id', $company->id)
                                        ->get()
                                        ->mapWithKeys(fn ($p) => [$p->id => $p->name]);
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('product_name', $product->name);
                                        $set('unit_price', $product->price);
                                    }
                                })
                                ->columnSpan(3),

                            Forms\Components\Hidden::make('product_name'),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Qtd')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Preço unit.')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->live()
                                ->columnSpan(2),
                        ])
                        ->columns(6)
                        ->required()
                        ->minItems(1)
                        ->addActionLabel('Adicionar produto')
                        ->visibleOn('create'),
                ])
                ->visibleOn('create'),

            Forms\Components\Section::make('Desconto e Observações')
                ->schema([
                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Desconto (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->default(0)
                        ->minValue(0)
                        ->visibleOn('create'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Status')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            Order::STATUS_PENDING => 'Pendente',
                            Order::STATUS_PROCESSING => 'Em separação',
                            Order::STATUS_SHIPPED => 'A caminho',
                            Order::STATUS_DELIVERED => 'Finalizado',
                            Order::STATUS_CANCELLED => 'Cancelado',
                        ])
                        ->required()
                        ->visibleOn('edit'),
                ])
                ->visibleOn('edit'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informações do Pedido')
                ->schema([
                    Infolists\Components\TextEntry::make('uuid')
                        ->label('ID do Pedido')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('client.name')
                        ->label('Cliente'),
                    Infolists\Components\TextEntry::make('channel')
                        ->label('Canal')
                        ->badge()
                        ->color(fn (string $state): string => $state === Order::CHANNEL_PRESENTIAL ? 'success' : 'info')
                        ->formatStateUsing(fn (string $state): string => $state === Order::CHANNEL_PRESENTIAL ? 'Presencial' : 'Online'),
                    Infolists\Components\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Order::STATUS_PENDING => 'warning',
                            Order::STATUS_PROCESSING => 'info',
                            Order::STATUS_SHIPPED => 'primary',
                            Order::STATUS_DELIVERED => 'success',
                            Order::STATUS_CANCELLED => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            Order::STATUS_PENDING => 'Pendente',
                            Order::STATUS_PROCESSING => 'Em separação',
                            Order::STATUS_SHIPPED => 'A caminho',
                            Order::STATUS_DELIVERED => 'Finalizado',
                            Order::STATUS_CANCELLED => 'Cancelado',
                            default => $state,
                        }),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Data do Pedido')
                        ->dateTime(),
                ])->columns(2),

            Infolists\Components\Section::make('Itens do Pedido')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')->label('Produto'),
                            Infolists\Components\TextEntry::make('quantity')->label('Qtd'),
                            Infolists\Components\TextEntry::make('unit_price')->label('Preço Unit.')->money('BRL'),
                            Infolists\Components\TextEntry::make('total_amount')->label('Subtotal')->money('BRL'),
                        ])->columns(4),
                ]),

            Infolists\Components\Section::make('Totais')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->label('Subtotal')->money('BRL'),
                    Infolists\Components\TextEntry::make('discount_amount')->label('Desconto')->money('BRL'),
                    Infolists\Components\TextEntry::make('fee_amount')->label('Taxas')->money('BRL'),
                    Infolists\Components\TextEntry::make('total_amount')->label('Total Final')->money('BRL'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->limit(8),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('channel')
                    ->label('Canal')
                    ->colors([
                        'success' => Order::CHANNEL_PRESENTIAL,
                        'info' => Order::CHANNEL_ONLINE,
                    ])
                    ->formatStateUsing(fn (string $state): string => $state === Order::CHANNEL_PRESENTIAL ? 'Presencial' : 'Online'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_PROCESSING => 'info',
                        Order::STATUS_SHIPPED => 'primary',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PROCESSING => 'Em separação',
                        Order::STATUS_SHIPPED => 'A caminho',
                        Order::STATUS_DELIVERED => 'Finalizado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PROCESSING => 'Em separação',
                        Order::STATUS_SHIPPED => 'A caminho',
                        Order::STATUS_DELIVERED => 'Finalizado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        Order::CHANNEL_ONLINE => 'Online',
                        Order::CHANNEL_PRESENTIAL => 'Presencial',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (Order $record): bool => $record->canBeApproved())
                    ->action(fn (Order $record) => $record->approve())
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('ship')
                    ->label('Despachar')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (Order $record): bool => $record->canBeShipped())
                    ->action(fn (Order $record) => $record->ship())
                    ->requiresConfirmation(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ManageOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
        ];
    }
}
