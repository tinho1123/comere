<?php

namespace App\Filament\Admin\Pages;

use App\Models\Product;
use App\Models\SupplierOrder;
use App\Services\StockReplenishmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            ->recordActions([
                Action::make('generate_order')
                    ->label('Gerar pedido')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('danger')
                    ->visible(fn (Product $record): bool => ($this->suggestionFor($record)['suggested_quantity'] ?? 0) > 0)
                    ->fillForm(fn (Product $record): array => [
                        'quantity_ordered' => $this->suggestionFor($record)['suggested_quantity'],
                    ])
                    ->schema([
                        TextInput::make('quantity_ordered')
                            ->label('Quantidade a pedir')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $suggestion = $this->suggestionFor($record);
                        $supplier = $record->supplier;

                        SupplierOrder::create([
                            'company_id' => Filament::getTenant()->id,
                            'supplier_id' => $supplier->id,
                            'product_id' => $record->id,
                            'quantity_suggested' => $suggestion['suggested_quantity'],
                            'quantity_ordered' => $data['quantity_ordered'],
                            'requested_at' => now(),
                            'expected_delivery_at' => now()->addDays($supplier->lead_time_days),
                            'status' => SupplierOrder::STATUS_PENDING,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Pedido registrado em Pedidos ao Fornecedor.')
                            ->send();
                    }),
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
