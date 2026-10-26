<?php

namespace App\Filament\Admin\Resources\StockLossResource\Pages;

use App\Filament\Admin\Resources\StockLossResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageStockLosses extends ManageRecords
{
    protected static string $resource = StockLossResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->extraAttributes(['data-tour' => 'perda-criar']),
        ];
    }
}
