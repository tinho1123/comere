<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\FavoredTransactionResource;
use App\Models\FavoredTransaction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FiadoPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Fiados';

    protected static ?string $title = 'Fiados';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.admin.pages.fiado-page';

    public ?string $selectedClient = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public function getTitle(): string
    {
        return $this->selectedClient
            ? 'Fiados — '.$this->selectedClient
            : 'Fiados';
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('new_fiado')
                ->label('Novo Fiado')
                ->icon('heroicon-o-plus')
                ->url(FavoredTransactionResource::getUrl('create'))
                ->color('primary'),
        ];

        if ($this->selectedClient) {
            array_unshift($actions, Action::make('back')
                ->label('Voltar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action(function (): void {
                    $this->selectedClient = null;
                    $this->resetTable();
                }));
        }

        return $actions;
    }

    public function selectClient(string $name): void
    {
        $this->selectedClient = $name;
        $this->resetTable();
    }

    public function getClientSummary(): ?object
    {
        if (! $this->selectedClient) {
            return null;
        }

        return FavoredTransaction::query()
            ->where('company_id', Filament::getTenant()->id)
            ->where('client_name', $this->selectedClient)
            ->selectRaw('
                COUNT(*) as items_count,
                SUM(favored_total) as total_debt,
                SUM(favored_paid_amount) as total_paid,
                (SUM(favored_total) - SUM(favored_paid_amount)) as remaining_balance
            ')
            ->first();
    }

    public function getClientStats(): array
    {
        $summary = $this->getClientSummary();

        return [
            Stat::make('Total Fiado', 'R$ '.number_format($summary?->total_debt ?? 0, 2, ',', '.'))
                ->icon('heroicon-o-credit-card'),

            Stat::make('Pago', 'R$ '.number_format($summary?->total_paid ?? 0, 2, ',', '.'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Saldo Devedor', 'R$ '.number_format($summary?->remaining_balance ?? 0, 2, ',', '.'))
                ->icon('heroicon-o-banknotes')
                ->color(($summary?->remaining_balance ?? 0) > 0 ? 'danger' : 'success'),
        ];
    }

    public function table(Table $table): Table
    {
        if ($this->selectedClient) {
            return $this->clientDetailTable($table);
        }

        return $this->clientListTable($table);
    }

    private function clientListTable(Table $table): Table
    {
        return $table
            ->query(
                FavoredTransaction::query()
                    ->where('company_id', Filament::getTenant()->id)
                    ->where('active', true)
                    ->selectRaw('
                        MIN(id) as id,
                        client_name,
                        COUNT(*) as items_count,
                        SUM(favored_total) as total_debt,
                        SUM(favored_paid_amount) as total_paid,
                        (SUM(favored_total) - SUM(favored_paid_amount)) as remaining_balance
                    ')
                    ->groupBy('client_name')
                    ->orderByRaw('remaining_balance DESC')
            )
            ->columns([
                TextColumn::make('client_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-user'),

                TextColumn::make('items_count')
                    ->label('Produtos')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_debt')
                    ->label('Total Fiado')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('total_paid')
                    ->label('Pago')
                    ->money('BRL')
                    ->color('success'),

                TextColumn::make('remaining_balance')
                    ->label('Saldo Devedor')
                    ->money('BRL')
                    ->weight(FontWeight::Bold)
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('view_client')
                    ->label('Ver Fiados')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action(fn (FavoredTransaction $record) => $this->selectClient($record->client_name)),
            ])
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateHeading('Nenhum fiado registrado')
            ->emptyStateDescription('Clique em "Novo Fiado" para registrar o primeiro.');
    }

    private function clientDetailTable(Table $table): Table
    {
        return $table
            ->query(
                FavoredTransaction::query()
                    ->where('company_id', Filament::getTenant()->id)
                    ->where('client_name', $this->selectedClient)
                    ->orderBy('due_date')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->searchable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('favored_total')
                    ->label('Total Fiado')
                    ->money('BRL'),

                TextColumn::make('favored_paid_amount')
                    ->label('Pago')
                    ->money('BRL')
                    ->color('success'),

                TextColumn::make('remaining')
                    ->label('Saldo')
                    ->getStateUsing(fn (FavoredTransaction $r): float => $r->getRemainingBalance())
                    ->money('BRL')
                    ->weight(FontWeight::Bold)
                    ->color(fn (FavoredTransaction $r): string => $r->getRemainingBalance() > 0 ? 'danger' : 'success'),

                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (FavoredTransaction $r): string => $r->due_date?->isPast() && $r->getRemainingBalance() > 0 ? 'danger' : 'gray'),
            ])
            ->actions([
                TableAction::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (FavoredTransaction $r): bool => ! $r->isFullyPaid() && $r->active)
                    ->form([
                        TextInput::make('amount')
                            ->label('Valor pago')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->minValue(0.01),
                    ])
                    ->action(function (FavoredTransaction $record, array $data): void {
                        $record->increment('favored_paid_amount', $data['amount']);
                        Notification::make()->title('Pagamento registrado!')->success()->send();
                    }),

                TableAction::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (FavoredTransaction $r): string => FavoredTransactionResource::getUrl('edit', ['record' => $r])),

                DeleteAction::make()->label('Excluir'),
            ])
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateHeading('Nenhum produto no fiado')
            ->emptyStateDescription('Este cliente não possui produtos em fiado.');
    }
}
