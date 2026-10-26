<?php

namespace App\Filament\Admin\Resources\SupplierExchangeResource\Pages;

use App\Filament\Admin\Resources\SupplierExchangeResource;
use App\Models\SupplierExchange;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageSupplierExchanges extends ManageRecords
{
    protected static string $resource = SupplierExchangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): SupplierExchange {
                    return DB::transaction(function () use ($data) {
                        $data['company_id'] = Filament::getTenant()->id;
                        $data['status'] = SupplierExchange::STATUS_PENDING;

                        $exchange = SupplierExchange::create($data);

                        $exchange->product()->decrement('quantity', $exchange->quantity);

                        return $exchange;
                    });
                }),
        ];
    }
}
