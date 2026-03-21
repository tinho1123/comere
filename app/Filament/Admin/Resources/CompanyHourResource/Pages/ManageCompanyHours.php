<?php

namespace App\Filament\Admin\Resources\CompanyHourResource\Pages;

use App\Filament\Admin\Resources\CompanyHourResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanyHours extends ManageRecords
{
    protected static string $resource = CompanyHourResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();

        $company = Filament::getTenant();

        if ($company->hours()->count() === 0) {
            foreach (range(0, 6) as $day) {
                $company->hours()->create([
                    'day_of_week' => $day,
                    'opens_at' => '08:00',
                    'closes_at' => '18:00',
                    'is_closed' => $day === 0, // Domingo fechado por padrão
                ]);
            }
        }
    }
}
