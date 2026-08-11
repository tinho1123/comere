---
name: Filament Resource Development
description: Guidelines for creating and managing Filament 4.x resources in Comere multi-tenant admin panel
---

# Filament Resource Development

This skill provides guidelines for creating Filament 4.x resources following Comere patterns for multi-tenant administration. See `.agent/skills/filament-v4.md` for the full v3→v4 migration notes and gotchas.

## Resource Structure

### Basic Resource Template

```php
<?php

namespace App\Filament\Client\Resources;

use App\Models\Product;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static ?string $navigationGroup = 'Catalog';
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
                
            Forms\Components\Textarea::make('description')
                ->rows(3),
                
            Forms\Components\Select::make('category_id')
                ->relationship('category', 'name')
                ->required(),
                
            Forms\Components\TextInput::make('price')
                ->numeric()
                ->prefix('R$')
                ->required(),
                
            Forms\Components\FileUpload::make('image')
                ->image()
                ->directory('products')
                ->maxSize(2048),
                
            Forms\Components\Toggle::make('active')
                ->default(true),
                
            Forms\Components\Toggle::make('featured')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->circular(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('price')
                    ->money('BRL')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                    
                Tables\Columns\IconColumn::make('featured')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                    
                Tables\Filters\TernaryFilter::make('active'),
                
                Tables\Filters\TernaryFilter::make('featured'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

## Common Form Components

### Text Input

```php
Forms\Components\TextInput::make('name')
    ->required()
    ->maxLength(255)
    ->placeholder('Enter product name')
    ->helperText('The display name for this product'),
```

### Textarea

```php
Forms\Components\Textarea::make('description')
    ->rows(3)
    ->maxLength(1000)
    ->columnSpanFull(),
```

### Select (Relationship)

```php
Forms\Components\Select::make('category_id')
    ->relationship('category', 'name')
    ->required()
    ->searchable()
    ->preload()
    ->createOptionForm([
        Forms\Components\TextInput::make('name')->required(),
    ]),
```

### Numeric Input

```php
Forms\Components\TextInput::make('price')
    ->numeric()
    ->prefix('R$')
    ->step(0.01)
    ->minValue(0)
    ->required(),
```

### Toggle

```php
Forms\Components\Toggle::make('active')
    ->default(true)
    ->inline(false),
```

### File Upload

```php
Forms\Components\FileUpload::make('image')
    ->image()
    ->directory('products')
    ->maxSize(2048) // 2MB
    ->imageEditor()
    ->imageEditorAspectRatios(['1:1', '16:9']),
```

### Date Picker

```php
Forms\Components\DatePicker::make('due_date')
    ->native(false)
    ->displayFormat('d/m/Y')
    ->minDate(now()),
```

### Repeater (for line items)

```php
Forms\Components\Repeater::make('items')
    ->relationship('items')
    ->schema([
        Forms\Components\Select::make('product_id')
            ->relationship('product', 'name')
            ->required(),
            
        Forms\Components\TextInput::make('quantity')
            ->numeric()
            ->default(1)
            ->minValue(1)
            ->required(),
            
        Forms\Components\TextInput::make('unit_price')
            ->numeric()
            ->prefix('R$')
            ->disabled(),
    ])
    ->columns(3)
    ->defaultItems(1)
    ->addActionLabel('Add Item'),
```

## Common Table Columns

### Text Column

```php
Tables\Columns\TextColumn::make('name')
    ->searchable()
    ->sortable()
    ->limit(50)
    ->tooltip(fn ($record) => $record->name),
```

### Money Column

```php
Tables\Columns\TextColumn::make('price')
    ->money('BRL')
    ->sortable(),
```

### Boolean Icon Column

```php
Tables\Columns\IconColumn::make('active')
    ->boolean()
    ->trueIcon('heroicon-o-check-circle')
    ->falseIcon('heroicon-o-x-circle')
    ->trueColor('success')
    ->falseColor('danger'),
```

### Badge Column (Status)

```php
Tables\Columns\BadgeColumn::make('status')
    ->colors([
        'danger' => 'cancelled',
        'warning' => 'pending',
        'success' => 'delivered',
        'primary' => 'processing',
    ])
    ->icons([
        'heroicon-o-x-circle' => 'cancelled',
        'heroicon-o-clock' => 'pending',
        'heroicon-o-check-circle' => 'delivered',
    ]),
```

### Image Column

```php
Tables\Columns\ImageColumn::make('image')
    ->circular()
    ->defaultImageUrl(url('/images/placeholder.png')),
```

### Relationship Column

```php
Tables\Columns\TextColumn::make('category.name')
    ->sortable()
    ->searchable(),
```

## Filters

### Select Filter

```php
Tables\Filters\SelectFilter::make('category')
    ->relationship('category', 'name')
    ->multiple()
    ->preload(),
```

### Ternary Filter (Boolean)

```php
Tables\Filters\TernaryFilter::make('active')
    ->placeholder('All products')
    ->trueLabel('Active only')
    ->falseLabel('Inactive only'),
```

### Date Range Filter

```php
Tables\Filters\Filter::make('created_at')
    ->form([
        Forms\Components\DatePicker::make('created_from'),
        Forms\Components\DatePicker::make('created_until'),
    ])
    ->query(function ($query, array $data) {
        return $query
            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
    }),
```

## Actions

### Table Actions

```php
Actions\EditAction::make(),

Actions\DeleteAction::make()
    ->requiresConfirmation(),

Actions\Action::make('activate')
    ->icon('heroicon-o-check')
    ->action(fn ($record) => $record->update(['active' => true]))
    ->requiresConfirmation()
    ->color('success'),
```

### Bulk Actions

```php
Actions\BulkActionGroup::make([
    Actions\DeleteBulkAction::make(),
    
    Actions\BulkAction::make('activate')
        ->icon('heroicon-o-check')
        ->action(fn (Collection $records) => $records->each->update(['active' => true]))
        ->deselectRecordsAfterCompletion(),
]),
```

## Multi-Tenancy Support

### Tenant-Scoped Resource

```php
use Filament\Facades\Filament;

class ProductResource extends Resource
{
    // Automatically scope queries to current tenant
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }
}
```

### Auto-Set company_id on Create

```php
// In CreateProduct page
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['company_id'] = Filament::getTenant()->id;
    
    return $data;
}
```

## Widgets

### Stats Widget

```php
<?php

namespace App\Filament\Client\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Products', Product::count())
                ->description('Active products in catalog')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('success'),
                
            Stat::make('Total Orders', Order::count())
                ->description('All time orders')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary'),
                
            Stat::make('Revenue', 'R$ ' . number_format(Order::sum('total_amount'), 2))
                ->description('Total revenue')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }
}
```

### Chart Widget

```php
<?php

namespace App\Filament\Client\Widgets;

use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    // Note: $heading is NOT static in ChartWidget on Filament v4 (unlike TableWidget).
    protected ?string $heading = 'Sales Trend';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [100, 150, 200, 180, 220, 250, 300],
                ],
            ],
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

## Navigation

### Grouping Resources

```php
protected static ?string $navigationGroup = 'Catalog';

protected static ?int $navigationSort = 1;
```

### Custom Navigation Label

```php
protected static ?string $navigationLabel = 'Products';

protected static ?string $pluralModelLabel = 'Products';

protected static ?string $modelLabel = 'Product';
```

## Validation

### Form Validation Rules

```php
Forms\Components\TextInput::make('email')
    ->email()
    ->required()
    ->unique(ignoreRecord: true),

Forms\Components\TextInput::make('cpf')
    ->required()
    ->mask('999.999.999-99')
    ->rules(['regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/']),
```

## Best Practices

### ✅ Do This

```php
// Use relationship() for foreign keys
Forms\Components\Select::make('category_id')
    ->relationship('category', 'name')

// Use money() for currency
Tables\Columns\TextColumn::make('price')
    ->money('BRL')

// Scope queries to tenant
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('company_id', Filament::getTenant()->id);
}
```

### ❌ Don't Do This

```php
// Don't manually load options
Forms\Components\Select::make('category_id')
    ->options(Category::pluck('name', 'id')) // Bad!

// Don't forget tenant scoping
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery(); // Missing tenant scope!
}
```

## References

- [Filament Documentation](https://filamentphp.com/docs)
- [Filament Form Builder](https://filamentphp.com/docs/forms)
- [Filament Table Builder](https://filamentphp.com/docs/tables)
- [Filament Multi-Tenancy](https://filamentphp.com/docs/panels/tenancy)
