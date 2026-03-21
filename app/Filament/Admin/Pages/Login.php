<?php

namespace App\Filament\Admin\Pages;

use App\Models\Company;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected static string $view = 'filament.admin.pages.login';

    public bool $showCompanySelector = false;

    /** @var array<int, array{uuid: string, name: string}> */
    public array $companyOptions = [];

    public ?string $selectedCompanyUuid = null;

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = auth()->user();

        $companies = $user->isMaster()
            ? Company::orderBy('name')->get()
            : $user->companies()->orderBy('name')->get();

        if ($companies->count() <= 1) {
            return $response;
        }

        $this->companyOptions = $companies
            ->map(fn (Company $c) => ['uuid' => $c->uuid, 'name' => $c->name])
            ->values()
            ->toArray();

        $this->showCompanySelector = true;

        return null;
    }

    public function selectCompany(): void
    {
        $this->validate(['selectedCompanyUuid' => ['required', 'string']]);

        $company = Company::where('uuid', $this->selectedCompanyUuid)->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->redirect('/admin/'.$company->uuid);
    }
}
