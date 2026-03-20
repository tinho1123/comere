<?php

namespace App\Filament\Master\Resources\CompanyResource\RelationManagers;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuários da loja';

    protected static ?string $modelLabel = 'Usuário';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->required()
                ->unique(User::class, 'email', ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->required()
                ->minLength(8),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo usuário')
                    ->using(function (array $data): Model {
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                        ]);

                        $this->getOwnerRecord()->users()->attach($user->id);

                        return $user;
                    }),
            ])
            ->actions([
                Action::make('change_password')
                    ->label('Trocar senha')
                    ->icon('heroicon-o-key')
                    ->form([
                        TextInput::make('password')
                            ->label('Nova senha')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),

                        TextInput::make('password_confirmation')
                            ->label('Confirmar nova senha')
                            ->password()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => Hash::make($data['password'])]);
                    }),

                DeleteAction::make()
                    ->label('Remover')
                    ->modalHeading('Remover usuário da loja')
                    ->modalDescription('O usuário será removido desta loja. Caso não pertença a nenhuma outra loja, sua conta será excluída.')
                    ->action(function (User $record): void {
                        $company = $this->getOwnerRecord();
                        $record->companies()->detach($company->id);

                        if ($record->companies()->count() === 0) {
                            $record->delete();
                        }
                    }),
            ]);
    }
}
