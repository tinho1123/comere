<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Dados de acesso')
                ->schema([
                    TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $context, ?string $state, Set $set) {
                            if ($context !== 'create' || blank($state)) {
                                return;
                            }

                            $existing = User::where('email', $state)->first();

                            if ($existing) {
                                $set('_email_exists', true);
                                $set('name', $existing->name);
                            } else {
                                $set('_email_exists', false);
                            }
                        })
                        ->disabled(fn (string $context) => $context === 'edit'),

                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Hidden::make('_email_exists')
                        ->default(false),

                    Placeholder::make('_notice_email_exists')
                        ->label('')
                        ->content('Este e-mail já está cadastrado. O usuário será vinculado a esta loja sem necessidade de senha.')
                        ->columnSpanFull()
                        ->visible(fn (Get $get, string $context) => $context === 'create' && (bool) $get('_email_exists')),

                    TextInput::make('password')
                        ->label('Senha')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->visible(fn (Get $get, string $context) => $context !== 'edit' && ! (bool) $get('_email_exists'))
                        ->required(fn (Get $get, string $context) => $context === 'create' && ! (bool) $get('_email_exists')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
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
            ->actions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $company = filament()->getTenant();

        return parent::getEloquentQuery()
            ->whereHas('companies', fn (Builder $q) => $q->where('companies.id', $company->id));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
        ];
    }
}
