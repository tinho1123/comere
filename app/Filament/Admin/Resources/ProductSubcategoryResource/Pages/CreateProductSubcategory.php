<?php

namespace App\Filament\Admin\Resources\ProductSubcategoryResource\Pages;

use App\Filament\Admin\Resources\ProductSubcategoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductSubcategory extends CreateRecord
{
    protected static string $resource = ProductSubcategoryResource::class;

    protected static ?string $title = 'Nova Subcategoria';

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
