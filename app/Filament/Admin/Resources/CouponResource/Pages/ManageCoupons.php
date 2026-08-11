<?php

namespace App\Filament\Admin\Resources\CouponResource\Pages;

use App\Filament\Admin\Resources\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageCoupons extends ManageRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['company_id'] = Filament::getTenant()->id;
                    $data['uuid'] = (string) Str::uuid();

                    return $data;
                }),
        ];
    }
}
