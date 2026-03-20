<?php

namespace App\Filament\Admin\Resources\FavoredTransactionResource\Pages;

use App\Filament\Admin\Resources\FavoredTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFavoredTransaction extends EditRecord
{
    protected static string $resource = FavoredTransactionResource::class;

    protected static ?string $title = 'Editar Fiado';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['client_name'] = $this->record->client?->name ?? $this->record->client_name;

        return $data;
    }
}
