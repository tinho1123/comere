<?php

namespace App\Filament\Master\Resources\BillingCycleResource\Pages;

use App\Filament\Master\Resources\BillingCycleResource;
use Filament\Resources\Pages\ListRecords;

class ManageBillingCycles extends ListRecords
{
    protected static string $resource = BillingCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
