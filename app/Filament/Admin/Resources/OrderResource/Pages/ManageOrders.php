<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\OrderResource\Widgets\FailedDeliveriesAlertWidget;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

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
}
