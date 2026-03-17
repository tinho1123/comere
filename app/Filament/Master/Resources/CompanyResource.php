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
use Illuminate\Support\Facades\Http;
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

    private static function geocodeAndSetCoords(callable $set, string $street, string $number, string $city, string $state): void
    {
        if (! $street || ! $city || ! $state) {
            return;
        }

        $query = implode(', ', array_filter([$number, $street, $city, $state, 'Brasil']));

        $response = Http::withHeaders(['User-Agent' => 'Comere/1.0 (comere.app)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'accept-language' => 'pt-BR',
            ]);

        if ($response->successful() && count($response->json()) > 0) {
            $geo = $response->json()[0];
            $set('latitude', round((float) $geo['lat'], 6));
            $set('longitude', round((float) $geo['lon'], 6));
        }
    }

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
                        ->maxLength(9)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                            $cep = preg_replace('/\D/', '', $state ?? '');

                            if (strlen($cep) !== 8) {
                                return;
                            }

                            $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");

                            if ($response->failed() || isset($response->json()['erro'])) {
                                return;
                            }

                            $data = $response->json();
                            $street = $data['logradouro'] ?? '';
                            $city = $data['localidade'] ?? '';
                            $state = strtoupper($data['uf'] ?? '');

                            $set('address_street', $street);
                            $set('address_neighborhood', $data['bairro'] ?? '');
                            $set('address_city', $city);
                            $set('address_state', $state);

                            static::geocodeAndSetCoords($set, $street, '', $city, $state);
                        }),

                    TextInput::make('address_street')
                        ->label('Rua / Avenida')
                        ->maxLength(255),

                    TextInput::make('address_number')
                        ->label('Número')
                        ->maxLength(20)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                            static::geocodeAndSetCoords(
                                $set,
                                $get('address_street') ?? '',
                                $state ?? '',
                                $get('address_city') ?? '',
                                $get('address_state') ?? ''
                            );
                        }),

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

                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->live()
                        ->helperText('Preenchido automaticamente'),

                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->live()
                        ->helperText('Preenchido automaticamente'),

                    Placeholder::make('location_map')
                        ->label('Localização no mapa')
                        ->columnSpan(3)
                        ->content(new HtmlString('
                            <div
                                x-data="{
                                    lat: null,
                                    lng: null,
                                    get mapSrc() {
                                        if (!this.lat || !this.lng) return null;
                                        const b = 0.004;
                                        return `https://www.openstreetmap.org/export/embed.html?bbox=${this.lng-b},${this.lat-b},${this.lng+b},${this.lat+b}&layer=mapnik&marker=${this.lat},${this.lng}`;
                                    }
                                }"
                                x-init="
                                    lat = parseFloat($wire.data?.latitude) || null;
                                    lng = parseFloat($wire.data?.longitude) || null;
                                    $wire.$watch(\'data.latitude\', v => { lat = parseFloat(v) || null; });
                                    $wire.$watch(\'data.longitude\', v => { lng = parseFloat(v) || null; });
                                "
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span x-show="lat && lng" x-cloak class="text-xs text-gray-400" x-text="`${lat}, ${lng}`"></span>
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
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500 px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 transition-colors ml-auto"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                                            <path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-2.013 3.5-4.749 3.5-8.318a6.5 6.5 0 00-13 0c0 3.569 1.555 6.305 3.5 8.318a19.517 19.517 0 002.683 2.282 16.975 16.975 0 001.144.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                        </svg>
                                        Usar GPS
                                    </button>
                                </div>

                                <div x-show="lat && lng" x-cloak class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700" style="height:220px">
                                    <iframe
                                        x-bind:src="mapSrc"
                                        width="100%"
                                        height="220"
                                        style="border:0;pointer-events:none"
                                        loading="lazy"
                                        title="Localização da loja"
                                    ></iframe>
                                </div>

                                <div x-show="!lat || !lng" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800" style="height:220px">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-gray-300 mb-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    <p class="text-sm text-gray-400">Preencha o CEP e o número para ver a localização</p>
                                </div>
                            </div>
                        ')),
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
