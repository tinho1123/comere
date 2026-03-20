<?php

namespace App\Filament\Admin\Resources\ProductSubcategoryResource\Pages;

use App\Filament\Admin\Resources\ProductSubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductSubcategory extends EditRecord
{
    protected static string $resource = ProductSubcategoryResource::class;

    protected static ?string $title = 'Editar Subcategoria';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
