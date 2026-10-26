<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SupplierOrderResource\Pages;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SupplierOrderResource extends Resource
{
    protected static ?string $model = SupplierOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pedidos ao Fornecedor';

    protected static ?string $modelLabel = 'Pedido ao Fornecedor';

    protected static ?string $pluralModelLabel = 'Pedidos ao Fornecedor';

    public static function getNavigationGroup(): ?string
    {
        return 'Estoque';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }

    public static function form(Schema $form): Schema
    {
        $companyId = Filament::getTenant()->id;

        return $form->schema([
            Section::make('Pedido ao Fornecedor')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->label('Fornecedor')
                        ->options(Supplier::where('company_id', $companyId)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabled(fn (?SupplierOrder $record): bool => $record !== null)
                        ->dehydrated(),

                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(fn (Get $get) => Product::where('company_id', $companyId)
                            ->when($get('supplier_id'), fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->disabled(fn (?SupplierOrder $record): bool => $record !== null)
                        ->dehydrated(),

                    Forms\Components\TextInput::make('quantity_ordered')
                        ->label('Quantidade pedida')
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    Forms\Components\DatePicker::make('requested_at')
                        ->label('Data do pedido')
                        ->native(false)
                        ->default(now())
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            static::updateExpectedDelivery($set, $get);
                        })
                        ->required(),

                    Forms\Components\DatePicker::make('expected_delivery_at')
                        ->label('Previsão de entrega')
                        ->helperText('Data do pedido + prazo de entrega do fornecedor.')
                        ->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    protected static function updateExpectedDelivery(Set $set, Get $get): void
    {
        $supplier = Supplier::find($get('supplier_id'));
        $requestedAt = $get('requested_at');

        if ($supplier && $requestedAt) {
            $set('expected_delivery_at', Carbon::parse($requestedAt)->addDays($supplier->lead_time_days)->format('Y-m-d'));
        }
    }

    public static function table(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;

        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->where('supplier_orders.company_id', $companyId)
                ->with(['product', 'supplier'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_ordered')
                    ->label('Quantidade pedida')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Pedido em')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_delivery_at')
                    ->label('Previsão de entrega')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => SupplierOrder::STATUS_PENDING,
                        'success' => SupplierOrder::STATUS_RECEIVED,
                        'danger' => SupplierOrder::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SupplierOrder::STATUS_RECEIVED => 'Recebido',
                        SupplierOrder::STATUS_CANCELLED => 'Cancelado',
                        default => 'Pendente',
                    }),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        SupplierOrder::STATUS_PENDING => 'Pendente',
                        SupplierOrder::STATUS_RECEIVED => 'Recebido',
                        SupplierOrder::STATUS_CANCELLED => 'Cancelado',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('mark_received')
                    ->label('Marcar recebido')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (SupplierOrder $record): bool => $record->status === SupplierOrder::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (SupplierOrder $record): void {
                        $record->markReceived();

                        Notification::make()
                            ->success()
                            ->title('Pedido recebido! Estoque atualizado.')
                            ->send();
                    }),

                Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SupplierOrder $record): bool => $record->status === SupplierOrder::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (SupplierOrder $record) => $record->update(['status' => SupplierOrder::STATUS_CANCELLED])),
            ])
            ->emptyStateHeading('Nenhum pedido registrado')
            ->emptyStateDescription('Gere um pedido a partir da tela de Sugestão de Reposição, ou crie um aqui.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSupplierOrders::route('/'),
        ];
    }
}
