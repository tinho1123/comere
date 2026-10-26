<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SupplierExchangeResource\Pages;
use App\Models\Product;
use App\Models\SupplierExchange;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierExchangeResource extends Resource
{
    protected static ?string $model = SupplierExchange::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Trocas com Fornecedor';

    protected static ?string $modelLabel = 'Troca com Fornecedor';

    protected static ?string $pluralModelLabel = 'Trocas com Fornecedor';

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
            Section::make('Troca com Fornecedor')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(Product::where('company_id', $companyId)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabled(fn (?SupplierExchange $record): bool => $record !== null)
                        ->dehydrated()
                        ->extraAttributes(['data-tour' => 'troca-fornecedor-produto']),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade')
                        ->helperText('Não pode ser maior que o estoque atual do produto.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): int => Product::find($get('product_id'))?->quantity ?? 1)
                        ->required()
                        ->disabled(fn (?SupplierExchange $record): bool => $record !== null)
                        ->dehydrated(),

                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da troca')
                        ->placeholder('Ex: defeito de fabricação')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações internas')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $companyId = Filament::getTenant()->id;

        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->where('supplier_exchanges.company_id', $companyId)
                ->with('product')
            )
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(40)
                    ->tooltip(fn (SupplierExchange $record): string => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => SupplierExchange::STATUS_PENDING,
                        'info' => SupplierExchange::STATUS_SENT,
                        'success' => SupplierExchange::STATUS_EXCHANGED,
                        'danger' => SupplierExchange::STATUS_REFUSED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SupplierExchange::STATUS_PENDING => 'Pendente',
                        SupplierExchange::STATUS_SENT => 'Enviado ao fornecedor',
                        SupplierExchange::STATUS_EXCHANGED => 'Trocado',
                        SupplierExchange::STATUS_REFUSED => 'Recusado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolvido em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        SupplierExchange::STATUS_PENDING => 'Pendente',
                        SupplierExchange::STATUS_SENT => 'Enviado ao fornecedor',
                        SupplierExchange::STATUS_EXCHANGED => 'Trocado',
                        SupplierExchange::STATUS_REFUSED => 'Recusado',
                    ]),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->visible(fn (SupplierExchange $record): bool => in_array($record->status, [SupplierExchange::STATUS_PENDING, SupplierExchange::STATUS_SENT])),

                Actions\Action::make('mark_sent')
                    ->label('Enviar ao fornecedor')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (SupplierExchange $record): bool => $record->status === SupplierExchange::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (SupplierExchange $record): void {
                        $record->update(['status' => SupplierExchange::STATUS_SENT]);

                        Notification::make()
                            ->success()
                            ->title('Troca marcada como enviada ao fornecedor.')
                            ->send();
                    }),

                Actions\Action::make('mark_exchanged')
                    ->label('Marcar trocado')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SupplierExchange $record): bool => in_array($record->status, [SupplierExchange::STATUS_PENDING, SupplierExchange::STATUS_SENT]))
                    ->requiresConfirmation()
                    ->action(function (SupplierExchange $record): void {
                        $record->product()->increment('quantity', $record->quantity);
                        $record->update([
                            'status' => SupplierExchange::STATUS_EXCHANGED,
                            'resolved_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Troca concluída! Estoque reposto.')
                            ->send();
                    }),

                Actions\Action::make('mark_refused')
                    ->label('Fornecedor recusou')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SupplierExchange $record): bool => in_array($record->status, [SupplierExchange::STATUS_PENDING, SupplierExchange::STATUS_SENT]))
                    ->requiresConfirmation()
                    ->action(function (SupplierExchange $record): void {
                        $record->update([
                            'status' => SupplierExchange::STATUS_REFUSED,
                            'resolved_at' => now(),
                        ]);

                        Notification::make()
                            ->warning()
                            ->title('Troca marcada como recusada pelo fornecedor.')
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->visible(fn (SupplierExchange $record): bool => $record->status === SupplierExchange::STATUS_PENDING),
            ])
            ->emptyStateHeading('Nenhuma troca registrada')
            ->emptyStateDescription('Registre aqui produtos com defeito de fabricação para troca com o fornecedor.')
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSupplierExchanges::route('/'),
        ];
    }
}
