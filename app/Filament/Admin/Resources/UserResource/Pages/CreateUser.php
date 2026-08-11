<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $company = filament()->getTenant();

        unset($data['_email_exists']);

        // E-mail já cadastrado: só vincula o usuário existente a esta loja se a senha
        // informada corresponder à senha real daquela conta — evita que qualquer admin
        // vincule (e ganhe exposição de dados para) uma conta de terceiro só por saber o e-mail.
        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            if (! Hash::check($data['password'] ?? '', $existing->password)) {
                throw ValidationException::withMessages([
                    'data.password' => 'Senha incorreta para a conta já cadastrada com este e-mail.',
                ]);
            }

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
