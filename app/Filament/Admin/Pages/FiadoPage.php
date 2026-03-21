<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\FavoredTransactionResource;
use App\Models\FavoredTransaction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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

    public function getClientSummaries(): Collection
    {
        return FavoredTransaction::query()
            ->where('company_id', Filament::getTenant()->id)
            ->where('active', true)
            ->selectRaw('
                client_name,
                COUNT(*) as items_count,
                SUM(favored_total) as total_debt,
                SUM(favored_paid_amount) as total_paid,
                (SUM(favored_total) - SUM(favored_paid_amount)) as remaining_balance
            ')
            ->groupBy('client_name')
            ->orderByRaw('remaining_balance DESC')
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FavoredTransaction::query()
                    ->where('company_id', Filament::getTenant()->id)
                    ->where('client_name', $this->selectedClient ?? '__no_match__')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                TextColumn::make('favored_total')
                    ->label('Total Fiado')
                    ->money('BRL'),

                TextColumn::make('favored_paid_amount')
                    ->label('Pago')
                    ->money('BRL'),

                TextColumn::make('remaining')
                    ->label('Saldo')
                    ->getStateUsing(fn (FavoredTransaction $r): float => $r->getRemainingBalance())
                    ->money('BRL')
                    ->color(fn (FavoredTransaction $r): string => $r->getRemainingBalance() > 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
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
            ->emptyStateHeading('Nenhum fiado encontrado')
            ->emptyStateDescription('Este cliente não possui fiados registrados.');
    }
}
