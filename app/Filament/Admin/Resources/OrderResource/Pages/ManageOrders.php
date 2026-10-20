<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\OrderResource\Widgets\FailedDeliveriesAlertWidget;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

    public function mount(): void
    {
        parent::mount();

        // Deep link usado pelo card de alerta de novo pedido (?pedido=<uuid>)
        // para abrir direto o modal de visualização do pedido.
        if ($uuid = request()->query('pedido')) {
            $record = $this->getTableQuery()->where('uuid', $uuid)->first();

            if ($record) {
                $this->mountTableAction('view', $record->getKey());
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FailedDeliveriesAlertWidget::class,
        ];
    }
}
