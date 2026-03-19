<?php

namespace App\Filament\Admin\Resources\PaymentSurchargeResource\Pages;

use App\Filament\Admin\Resources\PaymentSurchargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManagePaymentSurcharges extends ManageRecords
{
    protected static string $resource = PaymentSurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo acréscimo')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['uuid'] = (string) Str::uuid();

                    return $data;
                }),
        ];
    }
}
