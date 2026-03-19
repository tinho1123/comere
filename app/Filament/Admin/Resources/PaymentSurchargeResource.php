<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentSurchargeResource\Pages;
use App\Models\Order;
use App\Models\PaymentSurcharge;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentSurchargeResource extends Resource
{
    protected static ?string $model = PaymentSurcharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationLabel = 'Acréscimos por Pagamento';

    protected static ?string $modelLabel = 'Acréscimo';

    protected static ?string $pluralModelLabel = 'Acréscimos por Pagamento';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('payment_method')
                ->label('Método de pagamento')
                ->options(Order::paymentOptions())
                ->required()
                ->native(false),

            Select::make('type')
                ->label('Tipo de acréscimo')
                ->options([
                    'percent' => 'Porcentagem (%)',
                    'fixed' => 'Valor fixo (R$)',
                ])
                ->required()
                ->native(false),

            TextInput::make('amount')
                ->label('Valor')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->suffix(fn ($get) => $get('type') === 'percent' ? '%' : 'R$')
                ->required(),

            Toggle::make('active')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_method')
                    ->label('Método de pagamento')
                    ->formatStateUsing(fn (string $state): string => Order::paymentOptions()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'percent' ? 'info' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === 'percent' ? 'Porcentagem' : 'Valor fixo'),

                TextColumn::make('amount')
                    ->label('Acréscimo')
                    ->formatStateUsing(function (PaymentSurcharge $record): string {
                        if ($record->type === 'percent') {
                            return number_format($record->amount, 2, ',', '.').'%';
                        }

                        return 'R$ '.number_format($record->amount, 2, ',', '.');
                    }),

                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePaymentSurcharges::route('/'),
        ];
    }
}
