<?php

namespace App\Filament\Admin\Resources\FavoredTransactionResource\Pages;

use App\Filament\Admin\Resources\FavoredTransactionResource;
use App\Models\Client;
use App\Models\FavoredTransaction;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFavoredTransaction extends CreateRecord
{
    protected static string $resource = FavoredTransactionResource::class;

    protected static ?string $title = 'Registrar Fiado';

    protected function handleRecordCreation(array $data): Model
    {
        $companyId = Filament::getTenant()->id;

        $clientId = ! empty($data['client_id']) ? $data['client_id'] : null;
        $clientName = $data['client_name'] ?? null;

        if ($clientId && ! $clientName) {
            $clientName = Client::find($clientId)?->name;
        }

        $last = null;

        foreach ($data['items'] as $item) {
            $product = Product::find($item['product_id']);
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $price = (float) ($item['favored_price'] ?? $product?->favored_price ?? $product?->amount ?? 0);
            $total = round($price * $qty, 2);

            $last = FavoredTransaction::create([
                'company_id' => $companyId,
                'client_id' => $clientId,
                'client_name' => $clientName,
                'product_id' => $item['product_id'],
                'name' => $item['product_name'] ?? $product?->name,
                'category_id' => $product?->category_id,
                'category_name' => $product?->category?->name,
                'quantity' => $qty,
                'amount' => $price,
                'favored_total' => $total,
                'favored_paid_amount' => 0,
                'total_amount' => $total,
                'due_date' => $data['due_date'] ?? null,
                'active' => true,
            ]);
        }

        return $last;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
