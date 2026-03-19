<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TableSessionResource\Pages;
use App\Filament\Admin\Resources\TableSessionResource\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Models\TableSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
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
                        ->getStateUsing(fn (TableSession $record): string => 'R$ '.number_format($record->items()->sum('total_amount'), 2, ',', '.'))
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
                ])->columns(4),
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
                    ->modalDescription(fn (TableSession $record): string => 'Subtotal: R$ '.number_format($record->items()->sum('total_amount'), 2, ',', '.').'. Selecione o método de pagamento para calcular o total final.')
                    ->visible(fn (TableSession $record): bool => $record->isOpen())
                    ->form(function (TableSession $record): array {
                        $record->loadMissing('company');
                        $accepted = $record->company->getEffectivePaymentMethods();
                        $options = collect(Order::paymentOptions())
                            ->only($accepted)
                            ->all();

                        return [
                            Select::make('payment_method')
                                ->label('Método de pagamento')
                                ->options($options)
                                ->required()
                                ->native(false),
                        ];
                    })
                    ->action(function (TableSession $record, array $data): void {
                        $items = $record->items()->with('product')->get();
                        $subtotal = (float) $items->sum('total_amount');
                        $surchargeAmount = $record->calculateSurcharge($subtotal, $data['payment_method'], $items);
                        $total = $subtotal + $surchargeAmount;

                        $record->close($data['payment_method']);

                        $paymentLabel = Order::paymentOptions()[$data['payment_method']] ?? $data['payment_method'];
                        $body = 'Subtotal: R$ '.number_format($subtotal, 2, ',', '.').'. ';

                        if ($surchargeAmount > 0) {
                            $body .= 'Acréscimo: R$ '.number_format($surchargeAmount, 2, ',', '.').'. ';
                        }

                        $body .= 'Total: R$ '.number_format($total, 2, ',', '.').'. Pagamento: '.$paymentLabel.'.';

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
