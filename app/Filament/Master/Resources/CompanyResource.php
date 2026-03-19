<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\CompanyResource\Pages;
use App\Filament\Master\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use App\Services\BillingService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
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

    public static function canViewAny(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'master';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('tabs')->tabs([

                Tabs\Tab::make('Dados da Loja')->schema([
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
                ]),

                Tabs\Tab::make('Cobrança')
                    ->visibleOn('edit')
                    ->schema([
                        Section::make('Configuração de Cobrança')
                            ->description('Define o custo por pedido e o dia do vencimento mensal.')
                            ->schema([
                                TextInput::make('billingSetting.fee_per_transaction')
                                    ->label('Taxa por pedido (R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('Valor cobrado por cada pedido não cancelado no mês.'),

                                Select::make('billingSetting.payment_day')
                                    ->label('Dia de vencimento')
                                    ->options(collect(range(1, 28))->mapWithKeys(fn ($d) => [$d => "Dia {$d}"]))
                                    ->default(10)
                                    ->helperText('Dia do mês em que a fatura vence (máx. 28).'),
                            ])
                            ->columns(2),
                    ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Resumo da Loja')
                ->schema([
                    TextEntry::make('name')->label('Nome'),
                    TextEntry::make('address_city')->label('Cidade'),
                    TextEntry::make('billingSetting.fee_per_transaction')
                        ->label('Taxa por pedido')
                        ->money('BRL'),
                    TextEntry::make('billingSetting.payment_day')
                        ->label('Vencimento')
                        ->formatStateUsing(fn ($state) => $state ? "Dia {$state}" : '—'),
                ])->columns(4),

            InfoSection::make('Próximo vencimento & Mês atual')
                ->schema([
                    TextEntry::make('billing_next_due')
                        ->label('Próximo vencimento')
                        ->state(fn (Company $record): string => static::nextDueDate($record))
                        ->icon('heroicon-o-calendar-days'),

                    TextEntry::make('billing_current_total')
                        ->label('Custo mês atual')
                        ->state(fn (Company $record): string => static::currentMonthCost($record))
                        ->icon('heroicon-o-banknotes'),

                    TextEntry::make('billing_current_transactions')
                        ->label('Pedidos no mês')
                        ->state(fn (Company $record): string => static::currentMonthTransactions($record))
                        ->icon('heroicon-o-shopping-cart'),
                ])->columns(3),

            InfoSection::make('Histórico de cobranças')
                ->schema([
                    RepeatableEntry::make('billingCycles')
                        ->label('')
                        ->schema([
                            TextEntry::make('period_start')
                                ->label('Período')
                                ->formatStateUsing(fn ($state, $record) => $record->period_start->format('m/Y')),
                            TextEntry::make('transaction_count')->label('Pedidos'),
                            TextEntry::make('total_amount')->label('Total')->money('BRL'),
                            TextEntry::make('due_date')->label('Vencimento')->date('d/m/Y'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn ($state) => match ($state) {
                                    'paid' => 'success',
                                    'overdue' => 'danger',
                                    default => 'warning',
                                })
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'paid' => 'Pago',
                                    'overdue' => 'Em atraso',
                                    default => 'Pendente',
                                }),
                            TextEntry::make('payment_method')
                                ->label('Forma')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'cash' => 'Dinheiro',
                                    'pix' => 'PIX',
                                    'stripe' => 'Cartão',
                                    default => '—',
                                }),
                        ])->columns(6),
                ]),

            InfoSection::make('Métricas mensais')
                ->schema([
                    TextEntry::make('billing_metrics')
                        ->label('')
                        ->columnSpanFull()
                        ->state(fn (Company $record): string => static::buildMetricsHtml($record))
                        ->html(),
                ]),
        ]);
    }

    private static function nextDueDate(Company $company): string
    {
        $cycle = $company->billingCycles()->where('status', '!=', 'paid')->orderBy('due_date')->first();
        if ($cycle) {
            return $cycle->due_date->format('d/m/Y').' — R$ '.number_format($cycle->total_amount, 2, ',', '.');
        }

        $setting = $company->billingSetting;
        if (! $setting) {
            return '—';
        }
        $day = min($setting->payment_day, 28);

        return now()->addMonthNoOverflow()->setDay($day)->format('d/m/Y');
    }

    private static function currentMonthCost(Company $company): string
    {
        $cycle = $company->billingCycles()->where('period_start', now()->startOfMonth()->toDateString())->first();

        return $cycle ? 'R$ '.number_format($cycle->total_amount, 2, ',', '.') : 'R$ 0,00';
    }

    private static function currentMonthTransactions(Company $company): string
    {
        $cycle = $company->billingCycles()->where('period_start', now()->startOfMonth()->toDateString())->first();

        return $cycle ? (string) $cycle->transaction_count : '0';
    }

    private static function buildMetricsHtml(Company $company): string
    {
        $metrics = app(BillingService::class)->getMonthlyMetrics($company, 6);

        if (empty($metrics)) {
            return '<p class="text-sm text-gray-400">Nenhum dado disponível ainda.</p>';
        }

        $html = '<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">';
        foreach ($metrics as $m) {
            $changeHtml = '';
            if ($m['change_percent'] !== null) {
                $color = $m['change_percent'] > 0 ? 'text-danger-600' : 'text-success-600';
                $arrow = $m['change_percent'] > 0 ? '↑' : '↓';
                $changeHtml = "<span class=\"text-xs font-medium {$color}\">{$arrow} ".abs($m['change_percent']).'%</span>';
            }

            $statusColor = match ($m['status']) {
                'paid' => 'bg-success-50 border-success-200',
                'overdue' => 'bg-danger-50 border-danger-200',
                default => 'bg-warning-50 border-warning-200',
            };

            $html .= "
                <div class=\"rounded-xl border p-4 {$statusColor}\">
                    <p class=\"text-xs text-gray-500 mb-1\">{$m['period']}</p>
                    <p class=\"text-lg font-bold text-gray-800\">R$ ".number_format($m['total_amount'], 2, ',', '.')."</p>
                    <p class=\"text-xs text-gray-500\">{$m['transaction_count']} pedidos {$changeHtml}</p>
                </div>
            ";
        }
        $html .= '</div>';

        return $html;
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
                Action::make('view_billing')
                    ->label('Cobrança')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->url(fn (Company $record): string => static::getUrl('view', ['record' => $record])),
                Action::make('manage_users')
                    ->label('Usuários')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn (Company $record): string => static::getUrl('edit', ['record' => $record])),
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
            'view' => Pages\ViewCompany::route('/{record}'),
        ];
    }
}
