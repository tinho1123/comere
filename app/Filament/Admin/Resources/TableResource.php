<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TableResource\Pages;
use App\Models\Client;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TableResource extends Resource
{
    protected static ?string $model = TableModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Mesas';

    protected static ?string $modelLabel = 'Mesa';

    protected static ?string $pluralModelLabel = 'Mesas';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Mesas';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenant = filament()->getTenant();
            $count = TableSession::where('company_id', $tenant->id)
                ->where('status', 'open')
                ->count();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nome da Mesa')
                ->placeholder('ex: Mesa 1, Mesa VIP, Balcão...')
                ->required()
                ->maxLength(100),

            TextInput::make('seats')
                ->label('Capacidade (lugares)')
                ->numeric()
                ->minValue(1)
                ->placeholder('ex: 4'),

            Toggle::make('is_active')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('activeSession.client'))
            ->columns([
                TextColumn::make('name')
                    ->label('Mesa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('seats')
                    ->label('Lugares')
                    ->alignCenter()
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (TableModel $record): string => $record->activeSession ? 'occupied' : 'free')
                    ->color(fn (string $state): string => $state === 'occupied' ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'occupied' ? 'Ocupada' : 'Livre'),

                TextColumn::make('client_display')
                    ->label('Cliente')
                    ->getStateUsing(fn (TableModel $record): string => $record->activeSession
                        ? $record->activeSession->client_display_name
                        : '—'),
            ])
            ->actions([
                Action::make('open_session')
                    ->label('Abrir Mesa')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (TableModel $record): bool => $record->is_active && $record->activeSession === null)
                    ->form([
                        Select::make('client_id')
                            ->label('Cliente cadastrado')
                            ->options(function (): array {
                                $company = filament()->getTenant();

                                return Client::whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => $c->name.' — '.$c->document_number])
                                    ->toArray();
                            })
                            ->searchable()
                            ->placeholder('Selecionar cliente cadastrado'),

                        TextInput::make('guest_name')
                            ->label('Nome do cliente')
                            ->placeholder('ex: João Silva')
                            ->helperText('Preencha se o cliente não estiver cadastrado'),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (TableModel $record, array $data): void {
                        $session = TableSession::create([
                            'uuid' => str()->uuid(),
                            'company_id' => $record->company_id,
                            'table_id' => $record->id,
                            'client_id' => $data['client_id'] ?? null,
                            'guest_name' => $data['guest_name'] ?? null,
                            'status' => 'open',
                            'opened_at' => now(),
                            'total_amount' => 0,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Mesa aberta!')
                            ->success()
                            ->send();

                        redirect(TableSessionResource::getUrl('view', ['record' => $session->uuid]));
                    }),

                Action::make('view_session')
                    ->label('Ver Sessão')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (TableModel $record): bool => $record->activeSession !== null)
                    ->url(fn (TableModel $record): string => TableSessionResource::getUrl('view', [
                        'record' => $record->activeSession->uuid,
                    ])),

                Action::make('qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn (TableModel $record): string => 'QR Code — '.$record->name)
                    ->modalContent(function (TableModel $record): HtmlString {
                        $url = route('mesa.show', $record->uuid);
                        $id = 'qr-'.str_replace('-', '', $record->uuid);

                        return new HtmlString(<<<HTML
                            <div class="flex flex-col items-center gap-3 py-4">
                                <canvas id="{$id}"></canvas>
                                <p class="text-sm text-gray-500 break-all text-center">{$url}</p>
                            </div>
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSi2jPyeiKiqgB7KtKyZS/K4HQTAoEyAIlWg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    new QRCode(document.getElementById('{$id}'), {
                                        text: '{$url}',
                                        width: 220,
                                        height: 220,
                                    });
                                });
                                new QRCode(document.getElementById('{$id}'), {
                                    text: '{$url}',
                                    width: 220,
                                    height: 220,
                                });
                            </script>
                        HTML);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                EditAction::make()
                    ->label('Editar'),

                DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (TableModel $record): bool => $record->activeSession === null),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nova Mesa')
                    ->icon('heroicon-o-plus')
                    ->url(static::getUrl('create')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTables::route('/'),
            'create' => Pages\CreateTable::route('/create'),
            'edit' => Pages\EditTable::route('/{record}/edit'),
        ];
    }
}
