<?php

namespace App\Filament\Admin\Resources\DriverResource\Pages;

use App\Filament\Admin\Resources\DriverResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

    protected static ?string $title = 'Novo Motorista';

    protected function handleRecordCreation(array $data): Model
    {
        $data['company_id'] = Filament::getTenant()->id;

        return static::getModel()::create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
