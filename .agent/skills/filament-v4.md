# Filament v4 — Padrões e Armadilhas

## Versão usada neste projeto
Filament `^4.0` com Laravel 12, PHP 8.4, multi-tenant via `Company` (slug = uuid).

Este projeto migrou de v3 para v4 manualmente (sem o script Rector oficial). As seções abaixo já refletem a API v4 real, validada nesta migração.

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

O v4 introduz uma nova estrutura de diretórios padrão (resource/cluster fora de subpastas, com `php artisan filament:upgrade-directory-structure-to-v4`). Este projeto **mantém a estrutura antiga** (compatível, não obrigatório migrar).

---

## O que mudou de namespace (v3 → v4)

Essa é a mudança estrutural mais importante: **Forms e Infolists compartilham agora um pacote único de layout, `Filament\Schemas`.**

| Conceito | v3 | v4 |
|---|---|---|
| Container do form / infolist | `Filament\Forms\Form` / `Filament\Infolists\Infolist` | `Filament\Schemas\Schema` (mesma classe para os dois) |
| Layout: Grid, Section, Fieldset, Tabs, Tabs\Tab, Wizard, Group, Actions (wrapper) | `Filament\Forms\Components\*` / `Filament\Infolists\Components\*` | `Filament\Schemas\Components\*` |
| Campos de formulário (TextInput, Select, Toggle, FileUpload, Textarea, Placeholder, DatePicker, Repeater, Hidden, Checkbox, RichEditor, etc.) | `Filament\Forms\Components\*` | **inalterado** — continua em `Filament\Forms\Components\*` |
| Entradas de infolist (TextEntry, RepeatableEntry, IconEntry, ImageEntry, etc.) | `Filament\Infolists\Components\*` | **inalterado** — continua em `Filament\Infolists\Components\*` |
| `Get` / `Set` (closures reativas) | `Filament\Forms\Get` / `Filament\Forms\Set` | `Filament\Schemas\Components\Utilities\Get` / `...\Set` |
| Ações (Action, EditAction, DeleteAction, ViewAction, CreateAction, BulkAction, BulkActionGroup, DeleteBulkAction, etc.) | `Filament\Tables\Actions\*` | unificado em `Filament\Actions\*` (usado em tables, forms, pages — tudo igual) |
| Login customizado do painel | `Filament\Pages\Auth\Login` | `Filament\Auth\Pages\Login` |
| Contrato de resposta de login | `Filament\Http\Responses\Auth\Contracts\LoginResponse` | `Filament\Auth\Http\Responses\Contracts\LoginResponse` |

**Regra prática ao editar um Resource:** dentro do mesmo arquivo, `Section`/`Grid`/`Fieldset`/`Tabs` vêm de `Schemas\Components`, mas `TextInput`/`Select`/`TextEntry` continuam de `Forms\Components`/`Infolists\Components`. Não é um blanket-rename.

---

## Resources

### Resource padrão (CRUD com 3 páginas)

```php
use BackedEnum;
use Filament\Schemas\Schema;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Produtos';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestão';  // Define o grupo no menu lateral
    }

    public static function form(Schema $schema): Schema { ... }
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

**`$navigationIcon` mudou de tipo:** era `protected static ?string`, agora é `protected static string|BackedEnum|null` (precisa `use BackedEnum;`). Strings simples (`'heroicon-o-...'`) continuam funcionando — só o tipo da propriedade que precisa bater com a classe base, senão é fatal error ("Type of X::$navigationIcon must be BackedEnum|string|null"). O mesmo vale para `$activeNavigationIcon`.

**`$navigationGroup` como propriedade** (se você declarar direto, não via `getNavigationGroup()`) usa `string|UnitEnum|null` (precisa `use UnitEnum;` — nota: `UnitEnum`, não `BackedEnum`). Se você só sobrescreve o **método** `getNavigationGroup(): ?string`, não precisa mudar nada (retorno `?string` continua sendo um subtipo válido por covariância).

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
            Actions\CreateAction::make() // Filament\Actions\CreateAction, não Filament\Tables\Actions\CreateAction
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

## Formulários (Schema)

### Campos comuns (continuam em `Filament\Forms\Components\*`)

```php
use Filament\Forms\Components\{TextInput, Textarea, Select, Toggle, FileUpload, Placeholder};
use Filament\Schemas\Components\{Section, Grid, Tabs};
use Filament\Schemas\Components\Tabs\Tab;

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

### Seções e layout (`Filament\Schemas\Components\*`)

```php
Section::make('Configurações')
    ->description('Descrição opcional da seção')
    ->collapsible()        // permite recolher
    ->collapsed()          // começa recolhida
    ->columnSpanFull()     // v4: Section/Grid/Fieldset NÃO ocupam a largura total por padrão (mudou do v3)
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

**Mudança de comportamento padrão:** no v3, `Grid`/`Section`/`Fieldset` ocupavam todas as colunas do grid pai por padrão. No v4, ocupam **1 coluna** por padrão (como qualquer outro componente) — use `->columnSpanFull()` explicitamente quando precisar do comportamento antigo. Para preservar o padrão v3 em todo o projeto de uma vez, dá pra usar `configureUsing()` no `boot()` do `AppServiceProvider`:

```php
use Filament\Schemas\Components\{Fieldset, Grid, Section};

Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
Section::configureUsing(fn (Section $section) => $section->columnSpanFull());
```

### Ações inline dentro de um Schema (suffixAction, botão dentro de Section)

```php
use Filament\Actions\Action; // Filament\Actions\Action, não mais Forms\Components\Actions\Action
use Filament\Schemas\Components\Actions as SchemaActions; // wrapper de layout, se precisar agrupar botões

TextInput::make('address_zip')
    ->suffixAction(
        Action::make('buscar_cep')
            ->icon('heroicon-o-magnifying-glass')
            ->action('fillAddressFromZip')
    );
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
use Filament\Schemas\Components\Utilities\{Get, Set}; // não é mais Filament\Forms\Get/Set

TextInput::make('amount')
    ->live(onBlur: true)
    ->afterStateUpdated(fn ($state, Set $set) => $set('total', $state * 1.1))

Toggle::make('is_for_favored')
    ->live()

TextInput::make('favored_price')
    ->visible(fn (Get $get): bool => $get('is_for_favored'))
```

### `unique()` agora ignora o registro atual por padrão

No v3, `->unique()` **não** ignorava o registro sendo editado, a menos que você passasse `ignoreRecord: true`. No v4 esse é o comportamento padrão. Se algum form dependia do comportamento antigo (validar contra o próprio registro), passe `ignoreRecord: false`.

---

## Tabelas (Table)

Namespace de colunas/filtros (`Tables\Columns\*`, `Tables\Filters\*`) **não mudou**. Só as ações (`Tables\Actions\*` → `Actions\*`) e o padrão de `deferFilters`.

```php
use Filament\Actions\{Action, EditAction, ViewAction, BulkActionGroup, DeleteBulkAction};

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
    ->filters([
        // v4: filtros ficam "deferidos" por padrão — o usuário precisa clicar em Aplicar.
        // Para voltar ao comportamento instantâneo do v3: ->deferFilters(false)
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
    ->bulkActions([
        BulkActionGroup::make([
            DeleteBulkAction::make(),
        ]),
    ]);
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

### Paginação "todos" e ordenação padrão

- A opção `'all'` de itens por página não vem mais habilitada por padrão — adicione manualmente com `->paginationPageOptions([5, 10, 25, 50, 'all'])` se precisar (cuidado com performance em tabelas grandes).
- Tables agora ordenam pela chave primária por padrão como desempate (`defaultKeySort`). Desative com `->defaultKeySort(false)` se causar problema em alguma tabela sem PK simples.

### Parâmetros de URL renomeados

Se algum código monta URL manualmente com esses parâmetros (`::getUrl(...)` ou querystring direta), os nomes mudaram:

| v3 | v4 |
|---|---|
| `activeRelationManager` | `relation` |
| `activeTab` | `tab` |
| `isTableReordering` | `reordering` |
| `tableFilters` | `filters` |
| `tableGrouping` | `grouping` |
| `tableGroupingDirection` | `groupingDirection` |
| `tableSearch` | `search` |
| `tableSort` | `sort` |

---

## Infolist (página de visualização)

```php
use Filament\Infolists\Components\TextEntry; // entradas continuam aqui
use Filament\Schemas\Components\Section;     // layout vem de Schemas
use Filament\Schemas\Schema;

public static function infolist(Schema $schema): Schema
{
    return $schema->schema([
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

## Páginas Customizadas com Formulário — PADRÃO CORRETO v4

### Classe PHP

```php
namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class MySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Configurações';
    protected static ?string $title = 'Minhas Configurações';
    protected static ?int $navigationSort = 1;

    // v4: $view NÃO é mais estático (era `protected static string $view` no v3)
    protected string $view = 'filament.admin.pages.my-settings';

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

    public function form(Schema $schema): Schema
    {
        return $schema
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

### View Blade — padrão correto (inalterado do v3)

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
- `$view` na classe da página é `protected string $view` (instância, não estática) — declarar como `static` causa fatal error ("Cannot redeclare non static Filament\Pages\Page::$view as static")
- O botão Salvar fica DENTRO do `<form>` com `type="submit"`
- `->statePath('data')` no `form()` é obrigatório para bind correto

---

## Widgets

### TableWidget (tabela no dashboard)

`$heading` **continua estático** em `TableWidget` (diferente de `ChartWidget`, ver abaixo).

```php
class RecentOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Pedidos Recentes'; // estático — TableWidget não mudou aqui
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
            Action::make('ver_todos') // Filament\Actions\Action
                ->label('Ver todos')
                ->url(OrderResource::getUrl('index', ['tenant' => Filament::getTenant()])),
        ];
    }
}
```

### ChartWidget

**`$heading` e `$description` NÃO são mais estáticos** em `ChartWidget` (mudou do v3 — cuidado, é o oposto de `TableWidget`). Declarar como `static` causa fatal error ("Cannot redeclare non static Filament\Widgets\ChartWidget::$heading as static").

```php
class TransactionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Transações por Mês'; // NÃO estático em ChartWidget
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

### Dashboard customizado

```php
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // v4: getColumns() da classe base retorna int|array (SEM string).
    // Declarar int|string|array aqui é fatal error de covariância de retorno.
    public function getColumns(): int|array
    {
        return 2;
    }
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

### ⚠️ Escopo automático de tenant não é garantia de segurança

O v4 registra automaticamente um global scope por tenant para todo Resource do painel (via `BelongsToTenant::registerTenancyModelGlobalScope()`, chamado no `Panel::boot()`), usando a relação configurada em `ownershipRelationship` do `->tenant(...)` do panel provider (`'company'` neste projeto). **Isso não é garantido pela própria documentação do Filament** — o código-fonte do trait `BelongsToTenant` traz o aviso explícito: _"Filament does not guarantee multi-tenant security; it is your responsibility to implement correctly."_

Na prática, encontramos um caso real nesta migração: a closure do escopo automático captura a instância do objeto `Panel` no momento do registro. Em processo persistente (rodar a suite de testes inteira no mesmo processo PHP, e potencialmente Octane/queue workers em produção), se o `Panel::boot()` de uma execução anterior já registrou o scope, o registro **não roda de novo** para as execuções seguintes — e a comparação `Filament::getCurrentPanel() !== $panel` dentro da closure falha silenciosamente (identidade de objeto, não de ID), fazendo o filtro por tenant **não ser aplicado**, sem erro nenhum.

**Regra do projeto:** todo Resource deve ter um `getEloquentQuery()` explícito filtrando por `company_id` (ou pela relação de tenant), independente do escopo automático do Filament. Não confie só no automático.

```php
public static function getEloquentQuery(): Builder
{
    $company = filament()->getTenant();

    return parent::getEloquentQuery()
        ->where('company_id', $company->id);
}
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

**ERRO COMUM (herdado do v3, ainda vale):** Não adicionar ícone ao `NavigationGroup::make()` se os itens dentro já têm ícone. Filament lança exceção: _"Navigation group [X] has an icon but one or more of its items also have icons"_.

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
use Filament\Actions\{Action, CreateAction, DeleteAction}; // Filament\Actions, não Tables\Actions

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

## Armadilhas comuns (v4)

| Problema | Causa | Solução |
|---|---|---|
| `Class "Filament\Forms\Form" not found` | Classe removida no v4 | Trocar assinatura de `form()`/`infolist()` para `Schema $schema): Schema` (`use Filament\Schemas\Schema;`) |
| `Class "Filament\Tables\Actions\Action" not found` (ou EditAction, DeleteAction, etc.) | Ações unificadas em pacote próprio | `use Filament\Actions\Action;` (e demais) em vez de `Filament\Tables\Actions\*` |
| `Type of X::$navigationIcon must be BackedEnum\|string\|null` | Propriedade com tipo antigo `?string` | `protected static string\|BackedEnum\|null $navigationIcon` + `use BackedEnum;` |
| `Cannot redeclare non static ...::$view as static` | `$view` virou instância, não estática, em `Filament\Pages\Page` | `protected string $view` (sem `static`) |
| `Cannot redeclare static ...TableWidget::$heading as non static` | `$heading` continua estático em `TableWidget` (só `ChartWidget` mudou) | Não tirar o `static` de `$heading` em widgets de tabela |
| `Cannot redeclare non static ...ChartWidget::$heading as static` | `$heading`/`$description` não são mais estáticos em `ChartWidget` | Tirar o `static` da declaração |
| `Declaration of ...::getColumns() must be compatible` | `Dashboard::getColumns()` da base não aceita mais `string` | Trocar retorno para `int\|array` |
| Filtros da tabela exigem clicar em "Aplicar" | `deferFilters()` é `true` por padrão no v4 | `->deferFilters(false)` no `table()` se quiser voltar ao comportamento instantâneo |
| `Section`/`Grid`/`Fieldset` não ocupa mais a largura toda | Padrão mudou de "full" para "1 coluna" | Adicionar `->columnSpanFull()` onde precisar do comportamento antigo |
| Dado de outra empresa aparece na listagem (às vezes só em teste/worker persistente) | Escopo automático de tenant depende de identidade de objeto do `Panel`, não garantido pelo Filament | Adicionar `getEloquentQuery()` explícito filtrando por `company_id` no Resource (ver seção Multi-tenant acima) |
| Resource aparece no painel errado | Filament registra resources de todos os painéis | Adicione `canViewAny(): bool { return filament()->getCurrentPanel()?->getId() === 'admin'; }` |
| Campo JSON não salva | `->statePath('data')` ausente no `form()` | Adicione `->statePath('data')` ao retorno do `form()` |
| Coluna calculada em tabela | `getStateUsing` com closure não tipado | Tipar `fn (MyModel $record)` para evitar problemas com Livewire |
