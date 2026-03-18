<?php

namespace App\Filament\Master\Resources\ProductCategoryResource\Pages;

use App\Filament\Master\Resources\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova Categoria'),
        ];
    }
}
