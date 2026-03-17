<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $company = filament()->getTenant();

        $items = $data['items'] ?? [];

        $subtotal = collect($items)->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1));
        $discountAmount = $data['discount_amount'] ?? 0;
        $total = $subtotal - $discountAmount;

        $order = Order::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $data['client_id'],
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'fee_amount' => 0,
            'total_amount' => $total,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_PRESENTIAL,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($items as $item) {
            $unitPrice = $item['unit_price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $itemTotal = $unitPrice * $quantity;

            OrderItem::create([
                'uuid' => Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'total_amount' => $itemTotal,
            ]);
        }

        return $order;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
