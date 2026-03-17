<?php

namespace App\Filament\Admin\Resources\DeliveryFeeRangeResource\Pages;

use App\Filament\Admin\Resources\DeliveryFeeRangeResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryFeeRanges extends ManageRecords
{
    protected static string $resource = DeliveryFeeRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['company_id'] = Filament::getTenant()->id;

                    return $data;
                }),
        ];
    }
}
