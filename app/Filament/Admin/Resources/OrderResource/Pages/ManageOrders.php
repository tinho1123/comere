<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\OrderResource\Widgets\FailedDeliveriesAlertWidget;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\On;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

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

    #[On('comere-open-order')]
    public function openOrderFromAlert(string $recordId): void
    {
        $this->mountTableAction('view', $recordId);
    }
}
