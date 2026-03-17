<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\CompanyResource\Pages;
use App\Filament\Master\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Lojas';

    protected static ?string $modelLabel = 'Loja';

    protected static ?string $pluralModelLabel = 'Lojas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome da loja')
                ->required()
                ->maxLength(255),

            TextInput::make('admin_email')
                ->label('E-mail do administrador')
                ->email()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->maxLength(255)
                ->visibleOn('create'),

            TextInput::make('admin_password')
                ->label('Senha do administrador')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->visibleOn('create'),

            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory(fn ($record) => 'store/'.($record?->uuid ?? 'temp').'/images/logo')
                ->maxSize(512)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(400)
                ->imageResizeTargetHeight(400)
                ->imageResizeUpscale(false)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file) => (string) Str::uuid().'.'.$file->getClientOriginalExtension()
                )
                ->visibleOn('edit'),

            FileUpload::make('banner_path')
                ->label('Banner')
                ->image()
                ->disk('public')
                ->directory(fn ($record) => 'store/'.($record?->uuid ?? 'temp').'/images/banner')
                ->maxSize(2048)
                ->imageResizeMode('cover')
                ->imageResizeTargetWidth(1280)
                ->imageResizeTargetHeight(480)
                ->imageResizeUpscale(false)
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file) => (string) Str::uuid().'.'.$file->getClientOriginalExtension()
                )
                ->visibleOn('edit'),

            Section::make('Endereço da Loja')
                ->description('Preencha para calcular distância e taxas de entrega.')
                ->schema([
                    TextInput::make('address_zip')
                        ->label('CEP')
                        ->mask('99999-999')
                        ->maxLength(9),

                    TextInput::make('address_street')
                        ->label('Rua / Avenida')
                        ->maxLength(255),

                    TextInput::make('address_number')
                        ->label('Número')
                        ->maxLength(20),

                    TextInput::make('address_complement')
                        ->label('Complemento')
                        ->maxLength(100),

                    TextInput::make('address_neighborhood')
                        ->label('Bairro')
                        ->maxLength(100),

                    TextInput::make('address_city')
                        ->label('Cidade')
                        ->maxLength(100),

                    TextInput::make('address_state')
                        ->label('Estado (UF)')
                        ->maxLength(2)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    Placeholder::make('geo_button')
                        ->label('')
                        ->columnSpan(3)
                        ->content(new HtmlString('
                            <div x-data>
                                <button
                                    type="button"
                                    x-on:click="
                                        if (!navigator.geolocation) {
                                            alert(\'Geolocalização não suportada neste navegador.\');
                                            return;
                                        }
                                        navigator.geolocation.getCurrentPosition(
                                            pos => {
                                                $wire.set(\'data.latitude\', pos.coords.latitude.toFixed(6));
                                                $wire.set(\'data.longitude\', pos.coords.longitude.toFixed(6));
                                            },
                                            err => alert(\'Não foi possível obter a localização: \' + err.message)
                                        );
                                    "
                                    class="inline-flex items-center gap-2 rounded-lg border border-primary-500 px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                                        <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.013 3.5-4.749 3.5-8.318a6.5 6.5 0 00-13 0c0 3.569 1.555 6.305 3.5 8.318a19.517 19.517 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                    </svg>
                                    Usar localização atual
                                </button>
                            </div>
                        ')),

                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->helperText('Ex: -23.550520'),

                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->helperText('Ex: -46.633308'),
                ])
                ->columns(3)
                ->visibleOn('edit'),
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

                TextColumn::make('users.email')
                    ->label('Administrador')
                    ->searchable(),

                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
