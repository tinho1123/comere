<?php

namespace App\Filament\Master\Resources\CompanyResource\RelationManagers;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuários da loja';

    protected static ?string $modelLabel = 'Usuário';

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
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): string => $operation === 'edit'
                    ? 'Deixe em branco para manter a senha atual.'
                    : ''),
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

                IconColumn::make('is_master')
                    ->label('Master')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo usuário')
                    ->using(function (array $data, string $model): Model {
                        /** @var User $user */
                        $user = User::create($data);

                        $this->getOwnerRecord()->users()->attach($user->id);

                        return $user;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Remover da loja')
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
