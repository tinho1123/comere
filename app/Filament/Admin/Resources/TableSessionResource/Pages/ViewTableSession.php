<?php

namespace App\Filament\Admin\Resources\TableSessionResource\Pages;

use App\Filament\Admin\Resources\TableResource;
use App\Filament\Admin\Resources\TableSessionResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewTableSession extends ViewRecord
{
    protected static string $resource = TableSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_item')
                ->label('Adicionar Item')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->isOpen())
                ->form([
                    Select::make('product_id')
                        ->label('Produto')
                        ->options(function (): array {
                            return Product::where('company_id', $this->record->company_id)
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
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Preço unitário')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),

                    TextInput::make('product_name')
                        ->label('Nome no pedido')
                        ->required()
                        ->maxLength(200)
                        ->helperText('Preenchido automaticamente')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $qty = (int) $data['quantity'];

                    TableSessionItem::create([
                        'table_session_id' => $this->record->id,
                        'product_id' => $data['product_id'],
                        'product_name' => $data['product_name'],
                        'quantity' => $qty,
                        'unit_price' => $data['unit_price'],
                        'total_amount' => $data['unit_price'] * $qty,
                    ]);

                    Notification::make()
                        ->title($qty.'x '.$data['product_name'].' adicionado!')
                        ->success()
                        ->send();

                    $this->refreshFormData(['items']);
                }),

            Action::make('close_session')
                ->label('Fechar Mesa')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Fechar Mesa')
                ->modalDescription('Informe o método de pagamento e confirme o fechamento. O estoque será decrementado automaticamente.')
                ->visible(fn (): bool => $this->record->isOpen())
                ->form([
                    Select::make('payment_method')
                        ->label('Método de pagamento')
                        ->options(Order::paymentOptions())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    /** @var TableSession $session */
                    $session = $this->record;
                    $session->close($data['payment_method']);

                    $paymentLabel = Order::paymentOptions()[$data['payment_method']] ?? $data['payment_method'];

                    Notification::make()
                        ->title('Mesa fechada com sucesso!')
                        ->body('Pagamento: '.$paymentLabel.'. Total: R$ '.number_format($session->fresh()->total_amount, 2, ',', '.'))
                        ->success()
                        ->send();

                    $this->redirect(TableResource::getUrl('index'));
                }),

            Action::make('close_favored')
                ->label('Fechar - Fiado')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->modalHeading('Fechar Mesa como Fiado')
                ->modalDescription('Selecione como os itens serão cobrados no fiado. O débito será registrado no nome do cliente da mesa.')
                ->visible(fn (): bool => $this->record->isOpen())
                ->form(function (): array {
                    $items = $this->record->items()->with('product')->get();

                    $normalTotal = (float) $items->sum('total_amount');
                    $favoredTotal = (float) $items->sum(function ($item) {
                        if ($item->product && $item->product->favored_price) {
                            return (float) $item->product->favored_price * $item->quantity;
                        }

                        return (float) $item->total_amount;
                    });

                    $normalLabel = 'Preço normal — R$ '.number_format($normalTotal, 2, ',', '.');
                    $favoredLabel = 'Preço fiado — R$ '.number_format($favoredTotal, 2, ',', '.');

                    return [
                        Radio::make('pricing_mode')
                            ->label('Modo de cobrança')
                            ->options([
                                'normal' => $normalLabel,
                                'favored' => $favoredLabel,
                            ])
                            ->required()
                            ->live()
                            ->descriptions([
                                'normal' => 'Cobra o preço cheio de cada item.',
                                'favored' => 'Cobra o preço de fiado cadastrado em cada produto (fallback para preço normal quando não definido).',
                            ]),

                        Placeholder::make('items_preview')
                            ->label('Detalhes dos itens')
                            ->content(function (Get $get) use ($items): HtmlString {
                                $mode = $get('pricing_mode');

                                $rows = $items->map(function ($item) use ($mode) {
                                    $useFavored = $mode === 'favored'
                                        && $item->product
                                        && $item->product->favored_price;

                                    $unitPrice = $useFavored
                                        ? (float) $item->product->favored_price
                                        : (float) $item->unit_price;

                                    $lineTotal = $unitPrice * $item->quantity;

                                    $priceTag = $useFavored
                                        ? '<span class="text-yellow-600 font-semibold">fiado</span>'
                                        : '<span class="text-gray-400">normal</span>';

                                    return '<tr>
                                        <td class="py-1 pr-4 text-gray-700">'.e($item->product_name).'</td>
                                        <td class="py-1 pr-4 text-center text-gray-500">'.$item->quantity.'x</td>
                                        <td class="py-1 pr-4 text-right text-gray-600">R$ '.number_format($unitPrice, 2, ',', '.').'</td>
                                        <td class="py-1 pr-4 text-right font-semibold text-gray-800">R$ '.number_format($lineTotal, 2, ',', '.').'</td>
                                        <td class="py-1 text-right text-xs">'.$priceTag.'</td>
                                    </tr>';
                                })->implode('');

                                return new HtmlString('
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="text-xs text-gray-400 border-b border-gray-200">
                                                    <th class="pb-1 text-left">Produto</th>
                                                    <th class="pb-1 text-center">Qtd</th>
                                                    <th class="pb-1 text-right">Unitário</th>
                                                    <th class="pb-1 text-right">Total</th>
                                                    <th class="pb-1 text-right">Tipo</th>
                                                </tr>
                                            </thead>
                                            <tbody>'.$rows.'</tbody>
                                        </table>
                                    </div>
                                ');
                            }),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var TableSession $session */
                    $session = $this->record;
                    $session->closeAsFavored($data['pricing_mode']);

                    $fresh = $session->fresh();
                    $clientName = $session->client_display_name !== '—'
                        ? $session->client_display_name
                        : ($session->guest_name ?? 'Mesa');

                    Notification::make()
                        ->title('Mesa fechada como Fiado!')
                        ->body('Fiado registrado para "'.$clientName.'". Total: R$ '.number_format($fresh->total_amount, 2, ',', '.'))
                        ->warning()
                        ->send();

                    $this->redirect(TableResource::getUrl('index'));
                }),
        ];
    }
}
