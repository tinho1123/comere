<?php

namespace App\Filament\Admin\Resources\TableSessionResource\Pages;

use App\Filament\Admin\Resources\TableResource;
use App\Filament\Admin\Resources\TableSessionResource;
use App\Models\TableSession;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTableSession extends ViewRecord
{
    protected static string $resource = TableSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close_session')
                ->label('Fechar Mesa')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Fechar Mesa')
                ->modalDescription('Ao fechar a mesa, o estoque dos produtos será decrementado automaticamente. Deseja continuar?')
                ->visible(fn (): bool => $this->record->isOpen())
                ->action(function (): void {
                    /** @var TableSession $session */
                    $session = $this->record;
                    $session->close();

                    Notification::make()
                        ->title('Mesa fechada com sucesso!')
                        ->body('Estoque decrementado. Total: R$ '.number_format($session->fresh()->total_amount, 2, ',', '.'))
                        ->success()
                        ->send();

                    $this->redirect(TableResource::getUrl('index'));
                }),
        ];
    }
}
