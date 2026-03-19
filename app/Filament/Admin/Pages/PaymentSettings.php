<?php

namespace App\Filament\Admin\Pages;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Formas de Pagamento';

    protected static ?string $title = 'Formas de Pagamento Aceitas';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.payment-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Configurações';
    }

    public function mount(): void
    {
        $company = Filament::getTenant();
        $accepted = $company->accepted_payment_methods ?? array_keys(Order::paymentOptions());

        $this->form->fill([
            'accepted_payment_methods' => $accepted,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Métodos de pagamento aceitos')
                    ->description('Ative os métodos de pagamento disponíveis para sua loja. Os clientes só poderão selecionar os métodos habilitados.')
                    ->schema([
                        CheckboxList::make('accepted_payment_methods')
                            ->label('')
                            ->options(Order::paymentOptions())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $company = Filament::getTenant();
        $company->update([
            'accepted_payment_methods' => $this->data['accepted_payment_methods'] ?? [],
        ]);

        Notification::make()
            ->title('Configurações salvas!')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar')
                ->action('save'),
        ];
    }
}
