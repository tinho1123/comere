<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CouponResource\Pages;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Cupons';

    protected static ?string $modelLabel = 'Cupom';

    protected static ?string $pluralModelLabel = 'Cupons';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Filament::getTenant()->id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Cupom')
                ->schema([
                    TextInput::make('code')
                        ->label('Código')
                        ->required()
                        ->maxLength(50)
                        ->formatStateUsing(fn (?string $state): ?string => $state ? Str::upper($state) : $state)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state ? Str::upper(str_replace(' ', '', $state)) : $state)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', Filament::getTenant()->id))
                        ->placeholder('Ex: BEMVINDO10'),

                    Grid::make(2)
                        ->schema([
                            Select::make('discount_type')
                                ->label('Tipo de desconto')
                                ->options([
                                    Coupon::TYPE_PERCENT => 'Percentual (%)',
                                    Coupon::TYPE_FIXED => 'Valor fixo (R$)',
                                ])
                                ->default(Coupon::TYPE_PERCENT)
                                ->required()
                                ->live(),

                            TextInput::make('discount_value')
                                ->label('Valor do desconto')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->prefix(fn ($get) => $get('discount_type') === Coupon::TYPE_FIXED ? 'R$' : null)
                                ->suffix(fn ($get) => $get('discount_type') === Coupon::TYPE_PERCENT ? '%' : null)
                                ->maxValue(fn ($get) => $get('discount_type') === Coupon::TYPE_PERCENT ? 100 : null),
                        ]),
                ]),

            Section::make('Limites de uso')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('min_order_amount')
                                ->label('Pedido mínimo (R$)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('R$')
                                ->nullable()
                                ->placeholder('Sem mínimo'),

                            TextInput::make('max_uses')
                                ->label('Limite de usos')
                                ->numeric()
                                ->minValue(1)
                                ->nullable()
                                ->placeholder('Sem limite'),
                        ]),
                ])
                ->columns(2),

            Section::make('Validade')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            DateTimePicker::make('valid_from')
                                ->label('Válido a partir de')
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->nullable(),

                            DateTimePicker::make('valid_until')
                                ->label('Válido até')
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->nullable(),
                        ]),
                ])
                ->columns(2),

            Section::make('Status')
                ->schema([
                    Toggle::make('active')
                        ->label('Ativo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->icon('heroicon-o-ticket')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('discount_value')
                    ->label('Desconto')
                    ->formatStateUsing(fn (Coupon $record): string => $record->discount_type === Coupon::TYPE_PERCENT
                        ? number_format((float) $record->discount_value, 0).'%'
                        : 'R$ '.number_format((float) $record->discount_value, 2, ',', '.')),

                TextColumn::make('used_count')
                    ->label('Usos')
                    ->formatStateUsing(fn (Coupon $record): string => $record->max_uses
                        ? "{$record->used_count} / {$record->max_uses}"
                        : (string) $record->used_count),

                TextColumn::make('valid_until')
                    ->label('Expira em')
                    ->icon('heroicon-o-calendar')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(Width::Large),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhum cupom cadastrado')
            ->emptyStateDescription('Crie um cupom para oferecer descontos aos seus clientes.')
            ->emptyStateIcon('heroicon-o-ticket');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCoupons::route('/'),
        ];
    }
}
