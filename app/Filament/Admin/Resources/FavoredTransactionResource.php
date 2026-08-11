<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FavoredTransactionResource\Pages;
use App\Models\Client;
use App\Models\FavoredTransaction;
use App\Models\Product;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FavoredTransactionResource extends Resource
{
    protected static ?string $model = FavoredTransaction::class;

    protected static ?string $modelLabel = 'Fiado';

    protected static ?string $pluralModelLabel = 'Fiados';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Schemas\Components\Section::make('Devedor')
                ->schema([
                    Forms\Components\TextInput::make('client_name')
                        ->label('Nome de quem está devendo')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: João da Padaria')
                        ->helperText('Digite o nome livremente — não precisa ser um cliente cadastrado.'),

                    Forms\Components\Select::make('client_id')
                        ->label('Vincular a cliente cadastrado (opcional)')
                        ->options(function (): array {
                            $companyId = Filament::getTenant()->id;

                            return Client::whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->document_number.')'])
                                ->toArray();
                        })
                        ->searchable()
                        ->nullable()
                        ->placeholder('Nenhum — apenas nome livre')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if ($state) {
                                $client = Client::find($state);
                                $set('client_name', $client?->name);
                            }
                        }),
                ])
                ->visibleOn('create'),

            Forms\Components\TextInput::make('client_name')
                ->label('Devedor')
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),

            Forms\Components\DatePicker::make('due_date')
                ->label('Vencimento')
                ->nullable()
                ->displayFormat('d/m/Y'),

            Forms\Components\Repeater::make('items')
                ->label('Produtos')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(function (): array {
                            $companyId = Filament::getTenant()->id;

                            $products = Product::with('subcategory')
                                ->where('company_id', $companyId)
                                ->where('is_for_favored', true)
                                ->where('active', true)
                                ->orderBy('name')
                                ->get();

                            $grouped = [];
                            foreach ($products as $p) {
                                $group = $p->subcategory?->name ?? 'Outros';
                                $grouped[$group][$p->id] = $p->name.' — R$ '.number_format($p->favored_price ?? $p->amount, 2, ',', '.');
                            }
                            ksort($grouped);

                            return $grouped;
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (! $state) {
                                return;
                            }
                            $product = Product::find($state);
                            if ($product) {
                                $set('favored_price', number_format($product->favored_price ?? $product->amount, 2, '.', ''));
                                $set('product_name', $product->name);
                            }
                        })
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Qtd')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('favored_price')
                        ->label('Preço Fiado (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('product_name'),
                ])
                ->columns(4)
                ->minItems(1)
                ->addActionLabel('Adicionar produto')
                ->columnSpanFull()
                ->dehydrated(false)
                ->visibleOn('create'),

            Schemas\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Produto')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade')
                        ->numeric()
                        ->required(),
                ])
                ->visibleOn('edit'),

            Schemas\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('favored_total')
                        ->label('Valor Total do Fiado')
                        ->numeric()
                        ->prefix('R$')
                        ->required(),

                    Forms\Components\TextInput::make('favored_paid_amount')
                        ->label('Valor Pago')
                        ->numeric()
                        ->prefix('R$')
                        ->default(0),
                ])
                ->visibleOn('edit'),

            Forms\Components\Toggle::make('active')
                ->label('Ativo')
                ->default(true)
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('person_name')
                    ->label('Pessoa')
                    ->getStateUsing(fn (FavoredTransaction $record): string => $record->client?->name ?? $record->client_name ?? '—')
                    ->searchable(query: fn ($query, $search) => $query
                        ->whereHas('client', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('client_name', 'like', "%{$search}%"))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('favored_total')
                    ->label('Total Fiado')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('favored_paid_amount')
                    ->label('Pago')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Saldo')
                    ->getStateUsing(fn (FavoredTransaction $record): float => $record->getRemainingBalance())
                    ->money('BRL')
                    ->color(fn (FavoredTransaction $record): string => $record->getRemainingBalance() > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos'),
            ])
            ->actions([
                Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (FavoredTransaction $record): bool => ! $record->isFullyPaid() && $record->active)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor pago')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->minValue(0.01),
                    ])
                    ->action(function (FavoredTransaction $record, array $data): void {
                        $record->increment('favored_paid_amount', $data['amount']);
                    }),

                Actions\EditAction::make()->label('Editar'),
                Actions\DeleteAction::make()->label('Excluir'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $company = filament()->getTenant();

        return parent::getEloquentQuery()
            ->where('company_id', $company->id);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFavoredTransactions::route('/'),
            'create' => Pages\CreateFavoredTransaction::route('/create'),
            'edit' => Pages\EditFavoredTransaction::route('/{record}/edit'),
        ];
    }
}
