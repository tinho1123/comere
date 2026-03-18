<?php

namespace App\Filament\Admin\Resources\TableSessionResource\Pages;

use App\Filament\Admin\Resources\TableResource;
use App\Filament\Admin\Resources\TableSessionResource;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
                ->requiresConfirmation()
                ->modalHeading('Fechar Mesa')
                ->modalDescription('Ao fechar a mesa, o estoque dos produtos será decrementado automaticamente. Deseja continuar?')
                ->visible(fn (): bool => $this->record->isOpen())
                ->action(function (): void {
                    /** @var TableSession $session */
                    $session = $this->record;
                    $session->close();

                    Notification::make()
                        ->title('Mesa fechada com sucesso!')
                        ->body('Estoque decrementado. Total: R$ '.number_format($session->fresh()->total_amount, 2, ',', '.'))
                        ->success()
                        ->send();

                    $this->redirect(TableResource::getUrl('index'));
                }),
        ];
    }
}
