<?php

namespace App\Filament\Admin\Pages;

use App\Models\Product;
use App\Services\StockReplenishmentService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ReplenishmentSuggestions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Sugestão de Reposição';

    protected static ?string $title = 'Sugestão de Reposição';

    protected string $view = 'filament.admin.pages.replenishment-suggestions';

    /** @var array<int, array<string, mixed>> */
    protected array $suggestionCache = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Estoque';
    }

    public function table(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;

        return $table
            ->query(
                Product::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereNotNull('supplier_id')
                    ->with('supplier')
            )
            ->groups([
                Group::make('supplier.name')->label('Fornecedor'),
            ])
            ->defaultGroup('supplier.name')
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->weight('bold'),

                TextColumn::make('quantity')
                    ->label('Estoque atual')
                    ->suffix(' un'),

                TextColumn::make('velocity')
                    ->label('Venda média/dia')
                    ->state(fn (Product $record): string => $this->suggestionFor($record)['velocity'] !== null
                        ? number_format($this->suggestionFor($record)['velocity'], 1, ',', '.').' un/dia'
                        : '—'),

                TextColumn::make('season')
                    ->label('Sazonalidade')
                    ->badge()
                    ->color(fn (Product $record): string => $this->suggestionFor($record)['season_label'] === 'Estável' ? 'gray' : 'info')
                    ->state(fn (Product $record): string => $this->suggestionFor($record)['season_label']),

                TextColumn::make('suggestion')
                    ->label('Sugestão de pedido')
                    ->badge()
                    ->color(fn (Product $record): string => ($this->suggestionFor($record)['suggested_quantity'] ?? 0) > 0 ? 'danger' : 'success')
                    ->state(fn (Product $record): string => $this->suggestionFor($record)['label']),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('Nenhum produto com fornecedor cadastrado')
            ->emptyStateDescription('Associe um fornecedor aos produtos em Estoque → Produtos para ver sugestões aqui.');
    }

    protected function suggestionFor(Product $record): array
    {
        return $this->suggestionCache[$record->id] ??= app(StockReplenishmentService::class)->suggestionForProduct($record);
    }
}
