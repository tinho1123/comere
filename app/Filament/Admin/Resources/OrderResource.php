<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
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
            Forms\Components\Section::make('Tipo de pedido')
                ->schema([
                    Forms\Components\Select::make('channel')
                        ->label('Canal')
                        ->options([
                            Order::CHANNEL_ONLINE => 'Online (Delivery)',
                            Order::CHANNEL_PRESENTIAL => 'Presencial',
                        ])
                        ->default(Order::CHANNEL_PRESENTIAL)
                        ->native(false)
                        ->required()
                        ->live()
                        ->visibleOn('create'),
                ]),

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
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('client_address_id', null))
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('client.name')
                        ->label('Cliente')
                        ->disabled()
                        ->visibleOn('edit'),
                ]),

            Forms\Components\Section::make('Endereço de entrega')
                ->description('Obrigatório para pedidos online.')
                ->visibleOn('create')
                ->visible(fn (Get $get): bool => $get('channel') === Order::CHANNEL_ONLINE)
                ->schema([
                    Forms\Components\Select::make('client_address_id')
                        ->label('Usar endereço salvo do cliente')
                        ->options(function (Get $get): array {
                            $clientId = $get('client_id');
                            if (! $clientId) {
                                return [];
                            }

                            return ClientAddress::where('client_id', $clientId)
                                ->get()
                                ->mapWithKeys(fn ($a) => [
                                    $a->id => ($a->label ? "[$a->label] " : '')
                                        .$a->street.', '.$a->number
                                        .($a->complement ? " {$a->complement}" : '')
                                        ." — {$a->city}/{$a->state}",
                                ])
                                ->toArray();
                        })
                        ->placeholder('Ou preencha manualmente abaixo')
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (! $state) {
                                return;
                            }
                            $addr = ClientAddress::find($state);
                            if (! $addr) {
                                return;
                            }
                            $set('delivery_zip', $addr->zip_code);
                            $set('delivery_street', $addr->street);
                            $set('delivery_number', $addr->number);
                            $set('delivery_complement', $addr->complement);
                            $set('delivery_neighborhood', $addr->neighborhood);
                            $set('delivery_city', $addr->city);
                            $set('delivery_state', $addr->state);
                            $set('delivery_latitude', $addr->latitude);
                            $set('delivery_longitude', $addr->longitude);
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('delivery_zip')
                        ->label('CEP')
                        ->mask('99999-999')
                        ->required(fn (Get $get): bool => $get('channel') === Order::CHANNEL_ONLINE)
                        ->live(debounce: 600)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $cep = preg_replace('/\D/', '', $state ?? '');
                            if (strlen($cep) !== 8) {
                                return;
                            }
                            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");
                            if ($response->successful() && ! isset($response->json()['erro'])) {
                                $data = $response->json();
                                $set('delivery_street', $data['logradouro'] ?? null);
                                $set('delivery_neighborhood', $data['bairro'] ?? null);
                                $set('delivery_city', $data['localidade'] ?? null);
                                $set('delivery_state', $data['uf'] ?? null);
                                $set('delivery_latitude', null);
                                $set('delivery_longitude', null);
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('delivery_street')
                            ->label('Rua / Logradouro')
                            ->required(fn (Get $get): bool => $get('channel') === Order::CHANNEL_ONLINE)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('delivery_number')
                            ->label('Número')
                            ->required(fn (Get $get): bool => $get('channel') === Order::CHANNEL_ONLINE)
                            ->columnSpan(1),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('delivery_complement')
                            ->label('Complemento')
                            ->nullable()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('delivery_neighborhood')
                            ->label('Bairro')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('delivery_city')
                            ->label('Cidade')
                            ->columnSpan(1),
                    ]),

                    Forms\Components\TextInput::make('delivery_state')
                        ->label('Estado (UF)')
                        ->maxLength(2)
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('delivery_latitude'),
                    Forms\Components\Hidden::make('delivery_longitude'),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('geocode')
                            ->label('Buscar no mapa')
                            ->icon('heroicon-o-map-pin')
                            ->color('info')
                            ->action(function (Get $get, Set $set): void {
                                $parts = array_filter([
                                    trim(($get('delivery_street') ?? '').' '.($get('delivery_number') ?? '')),
                                    $get('delivery_neighborhood'),
                                    $get('delivery_city'),
                                    $get('delivery_state'),
                                    'Brasil',
                                ]);
                                $query = implode(', ', $parts);
                                if (! $query) {
                                    return;
                                }
                                $response = Http::withHeaders(['User-Agent' => 'Comere/1.0 contact@comere.app'])
                                    ->timeout(5)
                                    ->get('https://nominatim.openstreetmap.org/search', [
                                        'q' => $query,
                                        'format' => 'json',
                                        'limit' => 1,
                                        'countrycodes' => 'br',
                                    ]);
                                if ($response->successful() && count($response->json()) > 0) {
                                    $result = $response->json()[0];
                                    $set('delivery_latitude', $result['lat']);
                                    $set('delivery_longitude', $result['lon']);
                                } else {
                                    Notification::make()
                                        ->warning()
                                        ->title('Endereço não encontrado')
                                        ->body('Verifique os dados e tente novamente.')
                                        ->send();
                                }
                            }),
                    ]),

                    Forms\Components\Placeholder::make('map_preview')
                        ->label('Localização no mapa')
                        ->content(function (Get $get): HtmlString {
                            $lat = $get('delivery_latitude');
                            $lng = $get('delivery_longitude');
                            if (! $lat || ! $lng) {
                                return new HtmlString(
                                    '<div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800" style="height:110px">'
                                    .'<p class="text-sm text-gray-400">Preencha o endereço e clique em <strong>Buscar no mapa</strong></p>'
                                    .'</div>'
                                );
                            }
                            $latF = (float) $lat;
                            $lngF = (float) $lng;
                            $bbox = ($lngF - 0.005).','
                                .($latF - 0.005).','
                                .($lngF + 0.005).','
                                .($latF + 0.005);
                            $src = "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$latF},{$lngF}";

                            return new HtmlString(
                                '<div class="w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm" style="height:280px">'
                                ."<iframe src=\"{$src}\" style=\"width:100%;height:100%;border:0\" loading=\"lazy\"></iframe>"
                                .'</div>'
                                .'<p class="text-xs text-gray-500 mt-1">Lat: '.number_format($latF, 6).' &nbsp;|&nbsp; Lng: '.number_format($lngF, 6).'</p>'
                            );
                        })
                        ->columnSpanFull(),
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
