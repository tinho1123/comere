<?php

namespace App\Filament\Admin\Resources\ProductResource\Pages;

use App\Filament\Admin\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Editar Produto';

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

    /**
     * `attributesToArray()` (used by EditRecord to fill the form) respects
     * `Product::$hidden`, which deliberately hides `cost` from client-facing
     * serialization — but that also strips it here, silently blanking the
     * field on every edit and wiping the stored cost on save. Re-add it from
     * the record directly, since `$hidden` never affects attribute access.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cost'] = $this->getRecord()->cost;

        return $data;
    }
}
