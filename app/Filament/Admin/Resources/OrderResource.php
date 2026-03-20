<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                                        $set('unit_price', $product->amount);
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

            Forms\Components\Section::make('Pagamento e Observações')
                ->schema([
                    Forms\Components\Select::make('payment_method')
                        ->label('Método de pagamento')
                        ->options(Order::paymentOptions())
                        ->native(false),

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
                    Infolists\Components\TextEntry::make('payment_method')
                        ->label('Pagamento')
                        ->badge()
                        ->color('success')
                        ->formatStateUsing(fn (?string $state): string => $state ? (Order::paymentOptions()[$state] ?? $state) : '—')
                        ->placeholder('—'),

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

            Infolists\Components\View::make('filament.infolists.components.order-location')
                ->columnSpanFull(),

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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->limit(8),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
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
                Tables\Columns\TextColumn::make('notes')
                    ->label('Origem')
                    ->placeholder('—')
                    ->limit(30),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Order::paymentOptions()[$state] ?? $state) : '—')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery.driver.name')
                    ->label('Motorista')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->modalHeading('Despachar Pedido')
                    ->form(function (): array {
                        $companyId = Filament::getTenant()->id;

                        $drivers = Driver::where('company_id', $companyId)
                            ->where('is_active', true)
                            ->whereDoesntHave('deliveries', fn ($q) => $q->where('status', Delivery::STATUS_DISPATCHED))
                            ->get()
                            ->mapWithKeys(fn (Driver $d) => [
                                $d->id => $d->name.' — '.($d->vehicle_type === Driver::VEHICLE_MOTOBOY ? 'Motoboy' : 'Carro')
                                    .' — R$ '.number_format((float) $d->delivery_fee, 2, ',', '.'),
                            ])
                            ->toArray();

                        return [
                            Forms\Components\Select::make('driver_id')
                                ->label('Motorista disponível')
                                ->options($drivers)
                                ->native(false)
                                ->required()
                                ->helperText('Apenas motoristas ativos sem entrega em andamento.'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Observações para o motorista')
                                ->rows(2)
                                ->nullable(),
                        ];
                    })
                    ->action(function (Order $record, array $data): void {
                        $driver = Driver::findOrFail($data['driver_id']);

                        DB::transaction(function () use ($record, $driver, $data): void {
                            $record->ship();

                            Delivery::create([
                                'uuid' => (string) Str::uuid(),
                                'company_id' => $record->company_id,
                                'order_id' => $record->id,
                                'driver_id' => $driver->id,
                                'status' => Delivery::STATUS_DISPATCHED,
                                'driver_fee' => $driver->delivery_fee,
                                'is_paid' => false,
                                'dispatched_at' => now(),
                                'notes' => $data['notes'] ?? null,
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pedido despachado!')
                            ->body("Motorista: {$driver->name}")
                            ->send();
                    }),
                Tables\Actions\Action::make('deliver')
                    ->label('Concluir pedido')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->canBeDelivered())
                    ->action(fn (Order $record) => $record->deliver())
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
