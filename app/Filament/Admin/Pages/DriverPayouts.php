<?php

namespace App\Filament\Admin\Pages;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverPayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DriverPayouts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Repasses';

    protected static ?string $title = 'Repasses aos motoristas';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.driver-payouts';

    public ?int $selectedDriverId = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public function getTitle(): string
    {
        return $this->selectedDriver()
            ? 'Repasses — '.$this->selectedDriver()->name
            : 'Repasses aos motoristas';
    }

    protected function getHeaderActions(): array
    {
        if (! $this->selectedDriverId) {
            return [];
        }

        return [
            Action::make('back')
                ->label('Voltar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action(function (): void {
                    $this->selectedDriverId = null;
                    $this->resetTable();
                }),
        ];
    }

    public function selectDriver(int $driverId): void
    {
        $this->selectedDriverId = $driverId;
        $this->resetTable();
    }

    public function selectedDriver(): ?Driver
    {
        if (! $this->selectedDriverId) {
            return null;
        }

        return Driver::find($this->selectedDriverId);
    }

    public function getPayoutHistory(): Collection
    {
        return DriverPayout::where('company_id', Filament::getTenant()->id)
            ->with('driver')
            ->withCount('deliveries')
            ->latest('paid_at')
            ->limit(15)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $this->selectedDriverId
            ? $this->deliveriesTable($table)
            : $this->driversTable($table);
    }

    private function driversTable(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;

        return $table
            ->query(
                Driver::query()
                    ->whereHas('companies', fn (Builder $q) => $q
                        ->where('companies.id', $companyId)
                        ->where('driver_company.status', Driver::LINK_ACCEPTED))
                    ->withSum(['deliveries as pending_total' => fn (Builder $q) => $q
                        ->where('company_id', $companyId)
                        ->where('status', Delivery::STATUS_DELIVERED)
                        ->where('is_paid', false)], 'driver_fee')
                    ->withCount(['deliveries as pending_count' => fn (Builder $q) => $q
                        ->where('company_id', $companyId)
                        ->where('status', Delivery::STATUS_DELIVERED)
                        ->where('is_paid', false)])
                    ->orderByDesc('pending_total')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Motorista')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('vehicle_type')
                    ->label('Veículo')
                    ->badge()
                    ->color(fn (string $state): string => $state === Driver::VEHICLE_MOTOBOY ? 'warning' : 'info')
                    ->formatStateUsing(fn (string $state): string => $state === Driver::VEHICLE_MOTOBOY ? 'Motoboy' : 'Carro'),

                TextColumn::make('pending_count')
                    ->label('Entregas pendentes')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('pending_total')
                    ->label('Total a repassar')
                    ->money('BRL')
                    ->weight(FontWeight::Bold)
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'gray'),
            ])
            ->actions([
                Action::make('settle')
                    ->label('Acertar repasse')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (Driver $record): bool => (float) $record->pending_total > 0)
                    ->action(fn (Driver $record) => $this->selectDriver($record->id)),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('Nenhum motorista vinculado')
            ->emptyStateDescription('Convide motoristas em "Motoristas" para começar a despachar entregas.');
    }

    private function deliveriesTable(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;
        $driverId = $this->selectedDriverId;

        return $table
            ->query(
                Delivery::query()
                    ->where('company_id', $companyId)
                    ->where('driver_id', $driverId)
                    ->where('status', Delivery::STATUS_DELIVERED)
                    ->where('is_paid', false)
                    ->with('order')
            )
            ->columns([
                TextColumn::make('order.uuid')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state): string => '#'.strtoupper(substr($state, 0, 8))),

                TextColumn::make('delivered_at')
                    ->label('Entregue em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('driver_fee')
                    ->label('Taxa')
                    ->money('BRL')
                    ->weight(FontWeight::Bold),
            ])
            ->bulkActions([
                BulkAction::make('confirm_payout')
                    ->label('Confirmar repasse')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar repasse ao motorista')
                    ->form([
                        Select::make('method')
                            ->label('Método')
                            ->options(DriverPayout::methodOptions())
                            ->native(false)
                            ->required()
                            ->default(DriverPayout::METHOD_PIX),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (Collection $records, array $data) use ($companyId, $driverId): void {
                        $driver = Driver::findOrFail($driverId);

                        $payout = DB::transaction(function () use ($records, $data, $driver, $companyId) {
                            $payout = DriverPayout::create([
                                'company_id' => $companyId,
                                'driver_id' => $driver->id,
                                'total_amount' => $records->sum('driver_fee'),
                                'method' => $data['method'],
                                'notes' => $data['notes'] ?? null,
                                'paid_at' => now(),
                            ]);

                            $records->each->update([
                                'is_paid' => true,
                                'driver_payout_id' => $payout->id,
                            ]);

                            return $payout;
                        });

                        Notification::make()
                            ->success()
                            ->title('Repasse confirmado!')
                            ->body('R$ '.number_format((float) $payout->total_amount, 2, ',', '.')." para {$driver->name}.")
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Tudo em dia!')
            ->emptyStateDescription('Este motorista não tem entregas pendentes de repasse.');
    }
}
