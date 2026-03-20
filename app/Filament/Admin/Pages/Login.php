<?php

namespace App\Filament\Admin\Pages;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public bool $showStoreSelector = false;

    /** @var array<int, array<string, mixed>> */
    public array $availableStores = [];

    public ?int $selectedStoreId = null;

    public function form(Form $form): Form
    {
        if ($this->showStoreSelector) {
            return $form->schema([
                Select::make('selectedStoreId')
                    ->label('Selecione a loja')
                    ->options(
                        fn () => collect($this->availableStores)->pluck('name', 'id')->toArray()
                    )
                    ->native(false)
                    ->required()
                    ->searchable()
                    ->placeholder('Escolha uma loja para continuar'),
            ]);
        }

        return parent::form($form);
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = auth()->user();
        $tenants = $user->getTenants(filament()->getCurrentPanel());

        if ($tenants->count() <= 1) {
            return $response;
        }

        $this->availableStores = $tenants
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->toArray();

        $this->showStoreSelector = true;

        return null;
    }

    public function selectStore(): void
    {
        $this->validate(['selectedStoreId' => ['required', 'integer']]);

        $company = Company::find($this->selectedStoreId);

        if (! $company || ! auth()->user()->canAccessTenant($company)) {
            $this->addError('selectedStoreId', 'Loja inválida ou sem permissão de acesso.');

            return;
        }

        $this->redirect('/admin/'.$company->uuid);
    }

    protected function getFormActions(): array
    {
        if ($this->showStoreSelector) {
            return [
                Action::make('selectStore')
                    ->label('Entrar na loja')
                    ->submit('selectStore'),
            ];
        }

        return parent::getFormActions();
    }
}
