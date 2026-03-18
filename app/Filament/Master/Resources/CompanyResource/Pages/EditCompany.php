<?php

namespace App\Filament\Master\Resources\CompanyResource\Pages;

use App\Filament\Master\Resources\CompanyResource;
use App\Models\BillingSetting;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $setting = $this->record->billingSetting;

        $data['billingSetting'] = [
            'fee_per_transaction' => $setting?->fee_per_transaction ?? 0,
            'payment_day' => $setting?->payment_day ?? 10,
        ];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $billingData = $data['billingSetting'] ?? [];
        unset($data['billingSetting']);

        $record->update($data);

        if (! empty($billingData)) {
            BillingSetting::updateOrCreate(
                ['company_id' => $record->id],
                array_merge($billingData, ['uuid' => Str::uuid()])
            );
        }

        return $record;
    }
}
