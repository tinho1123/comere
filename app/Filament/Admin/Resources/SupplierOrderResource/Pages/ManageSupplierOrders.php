<?php

namespace App\Filament\Admin\Resources\SupplierOrderResource\Pages;

use App\Filament\Admin\Resources\SupplierOrderResource;
use App\Models\SupplierOrder;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageSupplierOrders extends ManageRecords
{
    protected static string $resource = SupplierOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()->id;
        $data['status'] = SupplierOrder::STATUS_PENDING;

        return $data;
    }
}
