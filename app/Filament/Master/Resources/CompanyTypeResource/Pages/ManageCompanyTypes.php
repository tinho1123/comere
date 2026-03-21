<?php

namespace App\Filament\Master\Resources\CompanyTypeResource\Pages;

use App\Filament\Master\Resources\CompanyTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanyTypes extends ManageRecords
{
    protected static string $resource = CompanyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo tipo'),
        ];
    }
}
