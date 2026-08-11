<?php

namespace App\Filament\Master\Resources\CompanyResource\Pages;

use App\Filament\Master\Resources\CompanyResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class ManageCompanyUsers extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;

    protected static string $relationship = 'users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $title = 'Usuários da loja';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para lojas')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CompanyResource::getUrl('index')),
        ];
    }

    public function form(Schema $form): Schema
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
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['password'] = Hash::make($data['password']);

                        return $data;
                    }),
            ])
            ->actions([
                TableAction::make('change_password')
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
                        Notification::make()->title('Senha alterada!')->success()->send();
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
