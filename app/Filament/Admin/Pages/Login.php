<?php

namespace App\Filament\Admin\Pages;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
        $companies = $user->isMaster()
            ? Company::all()
            : $user->companies()->get();

        if ($companies->count() <= 1) {
            return $response;
        }

        $this->mountAction('selectCompany');

        return null;
    }

    protected function getActions(): array
    {
        return [
            Action::make('selectCompany')
                ->label('Selecionar loja')
                ->modalHeading('Selecione uma loja')
                ->modalDescription('Você tem acesso a múltiplas lojas. Selecione em qual deseja entrar.')
                ->modalSubmitActionLabel('Entrar')
                ->modalCancelAction(false)
                ->closeModalByClickingAway(false)
                ->form([
                    Select::make('company_uuid')
                        ->label('Loja')
                        ->options(function (): array {
                            $user = auth()->user();

                            if (! $user) {
                                return [];
                            }

                            if ($user->isMaster()) {
                                return Company::orderBy('name')->pluck('name', 'uuid')->toArray();
                            }

                            return $user->companies()->orderBy('name')->pluck('name', 'uuid')->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->placeholder('Escolha uma loja para continuar'),
                ])
                ->action(function (array $data): void {
                    $company = Company::where('uuid', $data['company_uuid'])->firstOrFail();

                    abort_unless(auth()->user()->canAccessTenant($company), 403);

                    $this->redirect('/admin/'.$company->uuid);
                }),
        ];
    }
}
