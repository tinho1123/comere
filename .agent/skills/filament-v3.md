# Filament v3 — Padrões e Armadilhas

## Versão usada neste projeto
Filament `^3.3` com Laravel 10, PHP 8.4, multi-tenant via `Company` (slug = uuid).

---

## Estrutura de Arquivos

```
app/Filament/
├── Admin/
│   ├── Pages/          # Páginas customizadas do painel admin
│   ├── Resources/      # Resources do painel admin
│   │   └── XyzResource/
│   │       ├── Pages/
│   │       │   ├── CreateXyz.php
│   │       │   ├── EditXyz.php
│   │       │   ├── ListXyzs.php
│   │       │   └── ManageXyzs.php  # ManageRecords (CRUD em página única)
│   │       └── RelationManagers/
│   └── Widgets/
└── Master/
    └── Resources/
```

---

## Resources

### Resource padrão (CRUD com 3 páginas)

```php
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Produtos';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';  // Define o grupo no menu lateral
    }

    public static function form(Form $form): Form { ... }
    public static function table(Table $table): Table { ... }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

### Resource com ManageRecords (CRUD em página única — sem rotas create/edit separadas)

```php
// Resource
public static function getPages(): array
{
    return [
        'index' => Pages\ManageProducts::route('/'),
    ];
}

// Page
class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['uuid'] = (string) Str::uuid();
                    return $data;
                }),
        ];
    }
}
```

### Resource restrito a um painel específico (multi-painel)

Quando existem múltiplos painéis (admin + master), resources do master aparecem no admin para usuários `is_master`. Use `canViewAny()` para restringir:

```php
public static function canViewAny(): bool
{
    return filament()->getCurrentPanel()?->getId() === 'master';
}
```

---

## Formulários (Form)

### Campos comuns

```php
use Filament\Forms\Components\{
    TextInput, Textarea, Select, Toggle, FileUpload,
    Section, Grid, Placeholder, Tabs, Tab
};

// Texto
TextInput::make('name')->label('Nome')->required()->maxLength(255)

// Numérico com prefixo
TextInput::make('amount')->label('Preço')->numeric()->prefix('R$')->required()

// Select simples
Select::make('status')
    ->options(['active' => 'Ativo', 'inactive' => 'Inativo'])
    ->native(false)  // usa componente Filament (não o <select> nativo)
    ->required()

// Select com relacionamento
Select::make('category_id')
    ->relationship('category', 'name')
    ->searchable()
    ->preload()
    ->required()

// Toggle
Toggle::make('active')->label('Ativo')->default(true)

// Upload de imagem
FileUpload::make('image')
    ->image()
    ->disk('public')
    ->directory(fn () => 'store/' . Filament::getTenant()->uuid . '/products')
    ->maxSize(1024)

// Textarea
Textarea::make('description')->label('Descrição')->maxLength(65535)->columnSpanFull()

// Placeholder (texto estático no formulário)
Placeholder::make('_label')->label('')->content('Texto de ajuda')
```

### Seções e layout

```php
Section::make('Configurações')
    ->description('Descrição opcional da seção')
    ->collapsible()        // permite recolher
    ->collapsed()          // começa recolhida
    ->schema([...])

Grid::make(3)->schema([
    TextInput::make('a'),
    TextInput::make('b'),
    TextInput::make('c'),
])

// Tabs
Tabs::make('Abas')->tabs([
    Tab::make('Geral')->schema([...]),
    Tab::make('Avançado')->schema([...]),
])
```

### Campos JSON aninhados (dot notation)

Para modelos com `$casts = ['config' => 'array']`, o Filament suporta dot notation:

```php
// Acessa $record->payment_surcharges['cash']['amount']
TextInput::make('payment_surcharges.cash.amount')->numeric()
Select::make('payment_surcharges.cash.type')->options([...])
```

### Campos reativos (`live`)

```php
TextInput::make('amount')
    ->live(onBlur: true)
    ->afterStateUpdated(fn ($state, Set $set) => $set('total', $state * 1.1))

Toggle::make('is_for_favored')
    ->live()

TextInput::make('favored_price')
    ->visible(fn (Get $get): bool => $get('is_for_favored'))
```

---

## Tabelas (Table)

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->modifyQueryUsing(fn ($query) => $query->with(['client', 'company']))
    ->columns([
        TextColumn::make('name')->searchable()->sortable(),
        TextColumn::make('amount')->money('BRL'),
        IconColumn::make('active')->boolean(),
        TextColumn::make('status')
            ->badge()
            ->color(fn (string $state) => match($state) {
                'open'   => 'success',
                'closed' => 'gray',
                default  => 'warning',
            })
            ->formatStateUsing(fn (string $state) => match($state) {
                'open'   => 'Aberta',
                'closed' => 'Fechada',
                default  => $state,
            }),
        // Coluna calculada (não existe no banco)
        TextColumn::make('total_preview')
            ->label('Total')
            ->getStateUsing(fn (MyModel $record) => 'R$ ' . number_format($record->items->sum('amount'), 2, ',', '.')),
    ])
    ->actions([
        ViewAction::make()->label('Ver'),
        EditAction::make(),
        Action::make('close')
            ->label('Fechar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (MyModel $record) => $record->isOpen())
            ->action(fn (MyModel $record) => $record->close()),
    ])
```

### Action com modal e formulário

```php
Action::make('close')
    ->modalHeading('Fechar Mesa')
    ->modalDescription('Confirme o fechamento.')
    ->form([
        Select::make('payment_method')
            ->options(Order::paymentOptions())
            ->required()
            ->native(false),
    ])
    ->action(function (MyModel $record, array $data): void {
        $record->close($data['payment_method']);
        Notification::make()->title('Fechado!')->success()->send();
    })
```

### Action com form dinâmico (closure)

```php
->form(function (MyModel $record): array {
    $options = collect(Order::paymentOptions())
        ->only($record->company->getEffectivePaymentMethods())
        ->all();

    return [
        Select::make('payment_method')->options($options)->required()->native(false),
    ];
})
```

---

## Infolist (página de visualização)

```php
public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Section::make('Dados')
            ->schema([
                TextEntry::make('name')->label('Nome'),
                TextEntry::make('status')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                // Valor calculado
                TextEntry::make('total_preview')
                    ->label('Total')
                    ->getStateUsing(fn (MyModel $record) => 'R$ ' . number_format($record->total, 2, ',', '.')),
            ])->columns(2),
    ]);
}
```

---

## Páginas Customizadas com Formulário — PADRÃO CORRETO v3

**ERRO COMUM:** `getCachedFormActions()` e `hasFullWidthFormActions()` existem no Filament v2 mas **NÃO no v3**. Usar esses métodos em views causa erro 500.

### Classe PHP

```php
namespace App\Filament\Admin\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Configurações';
    protected static ?string $title = 'Minhas Configurações';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.admin.pages.my-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public function mount(): void
    {
        $company = Filament::getTenant();
        $this->form->fill([
            'some_field' => $company->some_field ?? 'default',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ... campos
            ])
            ->statePath('data');  // OBRIGATÓRIO — mapeia para $this->data
    }

    public function save(): void
    {
        $company = Filament::getTenant();
        $company->update($this->data);

        Notification::make()->title('Salvo!')->success()->send();
    }
}
```

### View Blade — padrão correto v3

```blade
{{-- resources/views/filament/admin/pages/my-settings.blade.php --}}
<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit">
                Salvar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

**Regras:**
- Use `wire:submit.prevent="save"` no `<form>`, não `wire:submit="save"` (evita reload)
- NÃO use `$this->getCachedFormActions()` — não existe em v3
- NÃO use `$this->hasFullWidthFormActions()` — não existe em v3
- O botão Salvar fica DENTRO do `<form>` com `type="submit"`
- `->statePath('data')` no `form()` é obrigatório para bind correto

---

## Widgets

### TableWidget (tabela no dashboard)

```php
class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Pedidos Recentes';
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->where('company_id', Filament::getTenant()->id)
            ->latest()
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
            TextColumn::make('total_amount')->money('BRL'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('ver_todos')
                ->label('Ver todos')
                ->url(OrderResource::getUrl('index', ['tenant' => Filament::getTenant()])),
        ];
    }
}
```

### ChartWidget

```php
class TransactionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Transações por Mês';
    protected string $dataChecksum = '';

    protected function getData(): array
    {
        $company = Filament::getTenant();
        $data = Order::where('company_id', $company->id)
            ->selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'datasets' => [
                ['label' => 'Total R$', 'data' => $data->values()->all()],
            ],
            'labels' => $data->keys()->map(fn ($m) => date('M', mktime(0,0,0,$m,1)))->all(),
        ];
    }

    protected function getType(): string { return 'bar'; }
}
```

---

## Notificações

```php
use Filament\Notifications\Notification;

Notification::make()
    ->title('Operação concluída!')
    ->body('Detalhes adicionais opcionais.')
    ->success()   // ou ->warning(), ->danger(), ->info()
    ->send();
```

---

## Multi-tenant (este projeto)

- Tenant = `Company`, slug = `uuid`
- Tenant atual: `Filament::getTenant()` (retorna `Company`)
- ID do tenant: `Filament::getTenant()->id`
- Queries SEMPRE filtradas por `company_id`
- URL do painel: `/admin/{company-uuid}/...`

```php
// Redirecionar para resource de outro painel mantendo tenant
->url(OrderResource::getUrl('index', ['tenant' => Filament::getTenant()]))

// Obter tenant em qualquer lugar do painel
$company = Filament::getTenant();
$company->id;      // int
$company->uuid;    // string
```

---

## Navegação e Grupos

### AdminPanelProvider — ordem dos grupos

```php
->navigationGroups([
    NavigationGroup::make('Mesas')->collapsible(),
    NavigationGroup::make('Vendas')->collapsible(),
    NavigationGroup::make('Gestão')->collapsible()->collapsed(),
    NavigationGroup::make('Configurações')->collapsible()->collapsed(),
])
```

**ERRO COMUM:** Não adicionar ícone ao `NavigationGroup::make()` se os itens dentro já têm ícone. Filament v3 lança exceção: _"Navigation group [X] has an icon but one or more of its items also have icons"_.

### Definir grupo no Resource

```php
public static function getNavigationGroup(): ?string
{
    return 'Vendas';
}
```

---

## RelationManagers

```php
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Itens';

    public function table(Table $table): Table
    {
        return $table
            ->columns([...])
            ->headerActions([CreateAction::make()])
            ->actions([
                Action::make('deliver')
                    ->label('Entregar')
                    ->action(fn (TableSessionItem $record) => $record->update([
                        'is_delivered' => true,
                        'delivered_at' => now(),
                    ]))
                    ->visible(fn (TableSessionItem $record) => ! $record->is_delivered),
                DeleteAction::make()
                    ->visible(fn (TableSessionItem $record) => ! $record->is_delivered),
            ]);
    }
}
```

---

## Armadilhas comuns

| Problema | Causa | Solução |
|---|---|---|
| Erro 500 em página customizada | `getCachedFormActions()` não existe no v3 | Use `<form wire:submit.prevent="save">` com botão `type="submit"` na view |
| `NavigationGroup` exception | Grupo com ícone + items com ícone | Remova o ícone do `NavigationGroup::make()` |
| Resource aparece no painel errado | Filament registra resources de todos os painéis | Adicione `canViewAny(): bool { return filament()->getCurrentPanel()?->getId() === 'admin'; }` |
| Campo JSON não salva | `->statePath('data')` ausente no `form()` | Adicione `->statePath('data')` ao retorno do `form()` |
| Coluna calculada em tabela | `getStateUsing` com closure não tipado | Tipar `fn (MyModel $record)` para evitar problemas com Livewire |
| Queries sem filtro de tenant | Esqueceu `company_id` | Sempre filtre por `Filament::getTenant()->id` ou use `modifyQueryUsing` |
