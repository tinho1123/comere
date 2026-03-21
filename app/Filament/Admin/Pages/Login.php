<?php

namespace App\Filament\Admin\Pages;

use App\Models\Company;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = auth()->user();

        $count = $user->isMaster()
            ? Company::count()
            : $user->companies()->count();

        if ($count <= 1) {
            return $response;
        }

        $this->redirect(route('admin.select-company'));

        return null;
    }
}
