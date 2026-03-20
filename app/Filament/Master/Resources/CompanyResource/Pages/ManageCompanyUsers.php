<?php

namespace App\Filament\Master\Resources\CompanyResource\Pages;

use App\Filament\Master\Resources\CompanyResource;
use Filament\Resources\Pages\ManageRelatedRecords;

class ManageCompanyUsers extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;

    protected static string $relationship = 'users';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $title = 'Usuários da loja';
}
