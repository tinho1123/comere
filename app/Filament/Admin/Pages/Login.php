<?php

namespace App\Filament\Admin\Pages;

use App\Models\Company;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected static string $view = 'filament.admin.pages.login';

    public bool $showCompanySelector = false;

    /** @var array<int, array{uuid: string, name: string, logo_path: string|null}> */
    public array $companyOptions = [];

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = auth()->user();

        $companies = $user->isMaster()
            ? Company::orderBy('name')->get(['uuid', 'name', 'logo_path'])
            : $user->companies()->orderBy('name')->get(['companies.uuid', 'companies.name', 'companies.logo_path']);

        if ($companies->count() <= 1) {
            return $response;
        }

        $this->companyOptions = $companies
            ->map(fn (Company $c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'logo_path' => $c->logo_path,
            ])
            ->values()
            ->toArray();

        $this->showCompanySelector = true;

        return null;
    }
}
