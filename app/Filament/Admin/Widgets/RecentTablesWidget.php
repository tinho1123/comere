<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\TableResource;
use App\Models\Table as TableModel;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTablesWidget extends BaseWidget
{
    protected static ?string $heading = 'Mesas';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        $company = filament()->getTenant();

        return $table
            ->query(
                TableModel::query()
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->withCount(['sessions as occupied' => fn ($q) => $q->where('status', 'open')])
                    ->with(['activeSession.client'])
                    ->orderByDesc('occupied')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                IconColumn::make('occupied')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-user-group')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->getStateUsing(fn (TableModel $record): bool => (bool) $record->occupied)
                    ->alignCenter(),

                TextColumn::make('name')
                    ->label('Mesa'),

                TextColumn::make('activeSession.client_display_name')
                    ->label('Cliente')
                    ->placeholder('—'),

                TextColumn::make('activeSession.items_count')
                    ->label('Itens')
                    ->getStateUsing(fn (TableModel $record): string => $record->activeSession
                        ? (string) $record->activeSession->items()->count()
                        : '—')
                    ->alignCenter(),

                TextColumn::make('activeSession.total_amount')
                    ->label('Total')
                    ->getStateUsing(fn (TableModel $record): string => $record->activeSession
                        ? 'R$ '.number_format($record->activeSession->items()->sum('total_amount'), 2, ',', '.')
                        : '—'),

                TextColumn::make('activeSession.opened_at')
                    ->label('Aberta em')
                    ->getStateUsing(fn (TableModel $record): string => $record->activeSession?->opened_at
                        ? $record->activeSession->opened_at->format('d/m H:i')
                        : '—'),
            ])
            ->headerActions([
                Action::make('ver_todas')
                    ->label('Ver todas')
                    ->url(fn () => TableResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray'),
            ]);
    }
}
