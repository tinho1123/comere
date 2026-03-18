<?php

namespace App\Filament\Admin\Resources\TableResource\Pages;

use App\Filament\Admin\Resources\TableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTable extends CreateRecord
{
    protected static string $resource = TableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = str()->uuid();
        $data['company_id'] = filament()->getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
