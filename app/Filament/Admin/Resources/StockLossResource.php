<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StockLossResource\Pages;
use App\Models\Product;
use App\Models\StockLoss;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLossResource extends Resource
{
    protected static ?string $model = StockLoss::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Perdas e Quebras';

    protected static ?string $modelLabel = 'Perda';

    protected static ?string $pluralModelLabel = 'Perdas e Quebras';

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
            Section::make('Perda / Quebra')
                ->description('Baixa de estoque por perda, vencimento ou quebra — não entra na conta de venda, então não distorce a sugestão de reposição.')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Produto')
                        ->options(Product::where('company_id', $companyId)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade perdida')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): int => Product::find($get('product_id'))?->quantity ?? 1)
                        ->required(),

                    Forms\Components\Select::make('reason')
                        ->label('Motivo')
                        ->options([
                            'vencido' => 'Vencido',
                            'quebrado' => 'Quebrado',
                            'outro' => 'Outro',
                        ])
                        ->native(false)
                        ->required(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
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
                ->where('stock_losses.company_id', $companyId)
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vencido' => 'Vencido',
                        'quebrado' => 'Quebrado',
                        default => 'Outro',
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStockLosses::route('/'),
        ];
    }
}
