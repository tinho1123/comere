<?php

namespace App\Filament\Admin\Resources\TableResource\Pages;

use App\Filament\Admin\Resources\TableResource;
use Filament\Resources\Pages\ListRecords;

class ListTables extends ListRecords
{
    protected static string $resource = TableResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
