<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\BillingCycleResource\Pages;
use App\Models\BillingCycle;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BillingCycleResource extends Resource
{
    protected static ?string $model = BillingCycle::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Cobranças';

    protected static ?string $modelLabel = 'Cobrança';

    protected static ?string $pluralModelLabel = 'Cobranças';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = BillingCycle::where('status', 'pending')
                ->where('due_date', '<=', now()->addDays(3)->toDateString())
                ->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'asc')
            ->columns([
                TextColumn::make('company.name')
                    ->label('Loja')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label('Período')
                    ->formatStateUsing(fn ($state, BillingCycle $record): string => $record->period_start->format('m/Y'))
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (BillingCycle $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('transaction_count')
                    ->label('Pedidos')
                    ->alignCenter(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'overdue' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'paid' => 'Pago',
                        'overdue' => 'Em atraso',
                        default => 'Pendente',
                    }),

                TextColumn::make('payment_method')
                    ->label('Forma')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash' => 'Dinheiro',
                        'pix' => 'PIX',
                        'stripe' => 'Cartão',
                        default => '—',
                    }),

                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'overdue' => 'Em atraso',
                    ]),

                SelectFilter::make('period')
                    ->label('Período')
                    ->options(fn () => static::periodOptions())
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('period_start', $data['value']);
                    }),
            ])
            ->actions([
                Action::make('confirm_payment')
                    ->label('Confirmar pagamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BillingCycle $record): bool => ! $record->isPaid())
                    ->form([
                        Select::make('payment_method')
                            ->label('Forma de pagamento')
                            ->options([
                                'cash' => 'Dinheiro',
                                'pix' => 'PIX',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (BillingCycle $record, array $data): void {
                        $record->update([
                            'status' => 'paid',
                            'payment_method' => $data['payment_method'],
                            'paid_at' => now(),
                            'confirmed_by' => Auth::id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Pagamento confirmado!')
                            ->success()
                            ->send();
                    }),

                Action::make('recalculate')
                    ->label('Recalcular')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (BillingCycle $record): bool => ! $record->isPaid())
                    ->action(function (BillingCycle $record): void {
                        app(BillingService::class)->recalculateCycle($record);

                        Notification::make()
                            ->title('Ciclo recalculado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('generate_current_month')
                    ->label('Gerar cobranças do mês')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(BillingService::class)->generateCurrentMonthForAll();
                        app(BillingService::class)->markOverdue();

                        Notification::make()
                            ->title('Cobranças geradas com sucesso!')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_confirm_payment')
                        ->label('Confirmar pagamento (PIX)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->filter(fn ($r) => ! $r->isPaid())
                                ->each(fn ($record) => $record->update([
                                    'status' => 'paid',
                                    'payment_method' => 'pix',
                                    'paid_at' => now(),
                                    'confirmed_by' => Auth::id(),
                                ]));

                            Notification::make()
                                ->title('Pagamentos confirmados!')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    private static function periodOptions(): array
    {
        return BillingCycle::selectRaw('DISTINCT period_start')
            ->orderByDesc('period_start')
            ->limit(12)
            ->pluck('period_start')
            ->mapWithKeys(fn ($d) => [
                ($d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d) => Carbon::parse($d)->format('m/Y'),
            ])
            ->toArray();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBillingCycles::route('/'),
        ];
    }
}
