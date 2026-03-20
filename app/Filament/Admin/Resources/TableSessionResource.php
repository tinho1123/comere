<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TableSessionResource\Pages;
use App\Filament\Admin\Resources\TableSessionResource\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Models\TableSession;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TableSessionResource extends Resource
{
    protected static ?string $model = TableSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Sessões';

    protected static ?string $modelLabel = 'Sessão de Mesa';

    protected static ?string $pluralModelLabel = 'Sessões de Mesa';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Mesas';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Mesa')
                ->schema([
                    TextEntry::make('table.name')
                        ->label('Mesa'),

                    TextEntry::make('client_display_name')
                        ->label('Cliente'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'open' ? 'success' : 'gray')
                        ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Aberta' : 'Fechada'),

                    TextEntry::make('opened_at')
                        ->label('Aberta em')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('closed_at')
                        ->label('Fechada em')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('notes')
                        ->label('Observações')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Resumo')
                ->schema([
                    TextEntry::make('items_count')
                        ->label('Itens')
                        ->getStateUsing(fn (TableSession $record): int => $record->items()->count()),

                    TextEntry::make('subtotal_preview')
                        ->label('Subtotal')
                        ->getStateUsing(fn (TableSession $record): string => 'R$ '.number_format($record->subtotal ?? $record->items()->sum('total_amount'), 2, ',', '.'))
                        ->color('primary'),

                    TextEntry::make('surcharge_preview')
                        ->label('Acréscimo')
                        ->getStateUsing(fn (TableSession $record): string => $record->surcharge_amount > 0 ? 'R$ '.number_format($record->surcharge_amount, 2, ',', '.') : '—')
                        ->color('warning'),

                    TextEntry::make('total_preview')
                        ->label('Total final')
                        ->getStateUsing(fn (TableSession $record): string => 'R$ '.number_format($record->total_amount ?? $record->items()->sum('total_amount'), 2, ',', '.'))
                        ->color('success')
                        ->weight('bold'),

                    TextEntry::make('payment_method')
                        ->label('Pagamento')
                        ->badge()
                        ->color('success')
                        ->formatStateUsing(fn (?string $state): string => $state ? (Order::paymentOptions()[$state] ?? $state) : '—')
                        ->placeholder('—'),
                ])->columns(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['table', 'client']))
            ->columns([
                TextColumn::make('table.name')
                    ->label('Mesa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client_display_name')
                    ->label('Cliente')
                    ->getStateUsing(fn (TableSession $record): string => $record->client_display_name),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'open' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Aberta' : 'Fechada'),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Order::paymentOptions()[$state] ?? $state) : '—')
                    ->placeholder('—'),

                TextColumn::make('opened_at')
                    ->label('Aberta em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('closed_at')
                    ->label('Fechada em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Aberta',
                        'closed' => 'Fechada',
                    ])
                    ->default('open'),
            ])
            ->actions([
                ViewAction::make()->label('Ver'),

                Action::make('close')
                    ->label('Fechar Mesa')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading('Fechar Mesa')
                    ->visible(fn (TableSession $record): bool => $record->isOpen())
                    ->form(function (TableSession $record): array {
                        return [
                            Select::make('payment_method')
                                ->label('Método de pagamento')
                                ->options(Order::paymentOptions())
                                ->required()
                                ->native(false)
                                ->live(),

                            Placeholder::make('summary')
                                ->label('Resumo')
                                ->content(function (Get $get) use ($record): HtmlString {
                                    $paymentMethod = $get('payment_method');
                                    $items = $record->items()->with('product')->get();
                                    $subtotal = (float) $items->sum('total_amount');

                                    if (! $paymentMethod) {
                                        return new HtmlString(
                                            '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">Selecione a forma de pagamento para ver o resumo.</div>'
                                        );
                                    }

                                    $surcharge = $record->calculateSurcharge($subtotal, $paymentMethod, $items);
                                    $total = $subtotal + $surcharge;

                                    $surchargeHtml = $surcharge > 0
                                        ? '<div class="flex justify-between font-medium text-orange-600"><span>Acréscimo</span><span>+ R$ '.number_format($surcharge, 2, ',', '.').'</span></div>'
                                        : '';

                                    return new HtmlString('
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-2 text-sm">
                                            <div class="flex justify-between text-gray-600"><span>Subtotal dos itens</span><span>R$ '.number_format($subtotal, 2, ',', '.').'</span></div>
                                            '.$surchargeHtml.'
                                            <div class="flex justify-between font-bold text-base border-t border-gray-200 pt-2 mt-1"><span>Total</span><span>R$ '.number_format($total, 2, ',', '.').'</span></div>
                                        </div>
                                    ');
                                }),
                        ];
                    })
                    ->action(function (TableSession $record, array $data): void {
                        $record->close($data['payment_method']);

                        $fresh = $record->fresh();
                        $paymentLabel = Order::paymentOptions()[$data['payment_method']] ?? $data['payment_method'];

                        $body = 'Subtotal: R$ '.number_format($fresh->subtotal, 2, ',', '.').'. ';
                        if ($fresh->surcharge_amount > 0) {
                            $body .= 'Acréscimo: R$ '.number_format($fresh->surcharge_amount, 2, ',', '.').'. ';
                        }
                        $body .= 'Total: R$ '.number_format($fresh->total_amount, 2, ',', '.').'. Pagamento: '.$paymentLabel.'.';

                        Notification::make()
                            ->title('Mesa fechada!')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTableSessions::route('/'),
            'view' => Pages\ViewTableSession::route('/{record}'),
        ];
    }
}
