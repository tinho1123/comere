<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CompanyHourResource\Pages;
use App\Models\CompanyHour;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyHourResource extends Resource
{
    protected static ?string $model = CompanyHour::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Horário de Funcionamento';

    protected static ?string $modelLabel = 'Horário';

    protected static ?string $pluralModelLabel = 'Horário de Funcionamento';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('is_closed')
                ->label('Fechado neste dia')
                ->reactive()
                ->columnSpanFull(),

            TimePicker::make('opens_at')
                ->label('Abre às')
                ->seconds(false)
                ->hidden(fn ($get) => $get('is_closed'))
                ->required(fn ($get) => ! $get('is_closed')),

            TimePicker::make('closes_at')
                ->label('Fecha às')
                ->seconds(false)
                ->hidden(fn ($get) => $get('is_closed'))
                ->required(fn ($get) => ! $get('is_closed')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->paginated(false)
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Dia')
                    ->formatStateUsing(fn ($state) => CompanyHour::DAY_NAMES[$state] ?? $state)
                    ->width('180px'),

                IconColumn::make('is_closed')
                    ->label('Fechado')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('opens_at')
                    ->label('Abre às')
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 5) : '—'),

                TextColumn::make('closes_at')
                    ->label('Fecha às')
                    ->formatStateUsing(fn ($state) => $state ? substr($state, 0, 5) : '—'),
            ])
            ->actions([
                EditAction::make()->label('Editar')->modalHeading(
                    fn (CompanyHour $record) => 'Horário — '.CompanyHour::DAY_NAMES[$record->day_of_week]
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCompanyHours::route('/'),
        ];
    }
}
