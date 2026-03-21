<?php

namespace App\Filament\Master\Resources;

use App\Filament\Master\Resources\CompanyResource\Pages;
use App\Filament\Master\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Models\Company;
use App\Models\CompanyType;
use App\Services\BillingService;
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

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Lojas';

    protected static ?string $modelLabel = 'Loja';

    protected static ?string $pluralModelLabel = 'Lojas';

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

                    Select::make('company_type_id')
                        ->label('Tipo de loja')
                        ->relationship('companyType', 'name')
                        ->options(CompanyType::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Selecione o tipo')
                        ->nullable(),

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
                    ->url(fn (Company $record): string => static::getUrl('users', ['record' => $record])),
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
            'users' => Pages\ManageCompanyUsers::route('/{record}/users'),
        ];
    }
}
