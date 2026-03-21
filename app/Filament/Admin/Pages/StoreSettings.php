<?php

namespace App\Filament\Admin\Pages;

use App\Models\CompanyType;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configurações da Loja';

    protected static ?string $title = 'Configurações da Loja';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.admin.pages.store-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $company = Filament::getTenant();

        $this->form->fill([
            'name' => $company->name,
            'description' => $company->description,
            'company_type_id' => $company->company_type_id,
            'delivery_time' => $company->delivery_time,
            'logo_path' => $company->logo_path,
            'banner_path' => $company->banner_path,
            'address_zip' => $company->address_zip,
            'address_street' => $company->address_street,
            'address_number' => $company->address_number,
            'address_complement' => $company->address_complement,
            'address_neighborhood' => $company->address_neighborhood,
            'address_city' => $company->address_city,
            'address_state' => $company->address_state,
            'latitude' => $company->latitude,
            'longitude' => $company->longitude,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identidade da Loja')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('logos')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('400')
                            ->imageResizeTargetHeight('400')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => 'logo_'.now()->timestamp.'.'.$file->getClientOriginalExtension()
                            ),

                        FileUpload::make('banner_path')
                            ->label('Banner')
                            ->image()
                            ->disk('public')
                            ->directory('banners')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('400')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => 'banner_'.now()->timestamp.'.'.$file->getClientOriginalExtension()
                            ),

                        TextInput::make('name')
                            ->label('Nome da loja')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Descrição')
                            ->maxLength(1000)
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('company_type_id')
                            ->label('Tipo de loja')
                            ->options(CompanyType::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione o tipo')
                            ->nullable(),

                        TextInput::make('delivery_time')
                            ->label('Tempo de entrega')
                            ->placeholder('Ex: 30-45 min')
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Section::make('Endereço e Localização')
                    ->description('Preencha o CEP para buscar o endereço automaticamente. Clique em "Localizar pelo GPS" para usar as coordenadas do seu dispositivo.')
                    ->schema([
                        TextInput::make('address_zip')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->maxLength(9)
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('buscar_cep')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->label('Buscar')
                                    ->action('fillAddressFromZip')
                            ),

                        TextInput::make('address_street')
                            ->label('Rua / Logradouro')
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
                            ->minLength(2),

                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->readOnly(),

                        Placeholder::make('map_preview')
                            ->label('Prévia no Mapa')
                            ->columnSpanFull()
                            ->content(function (): HtmlString {
                                $lat = $this->data['latitude'] ?? null;
                                $lng = $this->data['longitude'] ?? null;

                                if (! $lat || ! $lng) {
                                    return new HtmlString('<p class="text-sm text-gray-400 italic">Sem coordenadas definidas ainda.</p>');
                                }

                                return new HtmlString(
                                    '<iframe
                                        src="https://www.openstreetmap.org/export/embed.html?bbox='.($lng - 0.005).','.($lat - 0.005).','.($lng + 0.005).','.($lat + 0.005)."&layer=mapnik&marker={$lat},{$lng}\"
                                        width=\"100%\" height=\"220\" style=\"border:1px solid #e5e7eb; border-radius:8px;\" loading=\"lazy\">
                                    </iframe>"
                                );
                            }),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function fillAddressFromZip(): void
    {
        $zip = preg_replace('/\D/', '', $this->data['address_zip'] ?? '');

        if (strlen($zip) !== 8) {
            Notification::make()->title('CEP inválido')->warning()->send();

            return;
        }

        $response = Http::get("https://viacep.com.br/ws/{$zip}/json/");

        if ($response->failed() || isset($response->json()['erro'])) {
            Notification::make()->title('CEP não encontrado')->warning()->send();

            return;
        }

        $data = $response->json();

        $this->data['address_street'] = $data['logradouro'] ?? '';
        $this->data['address_neighborhood'] = $data['bairro'] ?? '';
        $this->data['address_city'] = $data['localidade'] ?? '';
        $this->data['address_state'] = $data['uf'] ?? '';

        $this->geocodeAndSetCoords();

        Notification::make()->title('Endereço preenchido!')->success()->send();
    }

    private function geocodeAndSetCoords(): void
    {
        $parts = array_filter([
            $this->data['address_street'] ?? '',
            $this->data['address_number'] ?? '',
            $this->data['address_neighborhood'] ?? '',
            $this->data['address_city'] ?? '',
            $this->data['address_state'] ?? '',
            'Brasil',
        ]);

        $query = implode(', ', $parts);

        $response = Http::withHeaders(['User-Agent' => 'Comere/1.0'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
            ]);

        if ($response->successful() && ! empty($response->json())) {
            $result = $response->json()[0];
            $this->data['latitude'] = (float) $result['lat'];
            $this->data['longitude'] = (float) $result['lon'];
        }
    }

    public function useGps(): void
    {
        // GPS coordinates are sent from Alpine.js via Livewire dispatch
        // This method is called with lat/lng emitted from the browser
    }

    public function setGpsCoords(float $lat, float $lng): void
    {
        $this->data['latitude'] = $lat;
        $this->data['longitude'] = $lng;

        Notification::make()->title('Coordenadas GPS atualizadas!')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar configurações')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $company = Filament::getTenant();

        $company->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'company_type_id' => $data['company_type_id'] ?? null,
            'delivery_time' => $data['delivery_time'] ?? null,
            'logo_path' => $data['logo_path'] ?? $company->logo_path,
            'banner_path' => $data['banner_path'] ?? $company->banner_path,
            'address_zip' => $data['address_zip'] ?? null,
            'address_street' => $data['address_street'] ?? null,
            'address_number' => $data['address_number'] ?? null,
            'address_complement' => $data['address_complement'] ?? null,
            'address_neighborhood' => $data['address_neighborhood'] ?? null,
            'address_city' => $data['address_city'] ?? null,
            'address_state' => $data['address_state'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        Notification::make()
            ->title('Configurações salvas com sucesso!')
            ->success()
            ->send();
    }
}
