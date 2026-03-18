<?php

namespace App\Filament\Admin\Resources\TableSessionResource\Pages;

use App\Filament\Admin\Resources\TableSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListTableSessions extends ListRecords
{
    protected static string $resource = TableSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
