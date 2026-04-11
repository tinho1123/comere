<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $company = filament()->getTenant();

        unset($data['_email_exists']);

        // E-mail já cadastrado: vincula usuário existente sem criar novo
        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            $company->users()->syncWithoutDetaching([$existing->id]);

            Notification::make()
                ->title('Usuário vinculado')
                ->body("O usuário {$existing->name} foi vinculado a esta loja.")
                ->success()
                ->send();

            return $existing;
        }

        // Novo e-mail: cria o usuário e vincula à loja atual
        /** @var User $user */
        $user = User::create($data);
        $company->users()->attach($user->id);

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
