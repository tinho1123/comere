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
     * `Product::$hidden`, which deliberately hides `cost` and the
     * replenishment fields from client-facing serialization — but that also
     * strips them here, silently blanking them on every edit and wiping the
     * stored values on save (e.g. `purchase_unit_size` is NOT NULL, so this
     * previously made every edit fail). Re-add them from the record
     * directly, since `$hidden` never affects attribute access.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['cost'] = $record->cost;
        $data['supplier_id'] = $record->supplier_id;
        $data['purchase_unit_size'] = $record->purchase_unit_size;
        $data['min_stock'] = $record->min_stock;

        return $data;
    }
}
