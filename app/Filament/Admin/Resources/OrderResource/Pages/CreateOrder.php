<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $company = filament()->getTenant();

        $items = $data['items'] ?? [];
        $channel = $data['channel'] ?? Order::CHANNEL_PRESENTIAL;

        // Pedido presencial já foi vendido e entregue no balcão, então nasce finalizado
        // sem passar pelas etapas de aprovação/despacho usadas nos pedidos online.
        $isPresential = $channel === Order::CHANNEL_PRESENTIAL;

        if ($isPresential && ($message = Product::findInsufficientStockMessage($items, $company->id))) {
            Notification::make()
                ->title('Não foi possível criar o pedido')
                ->body($message)
                ->danger()
                ->send();

            throw new Halt;
        }

        $subtotal = collect($items)->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1));
        $discountAmount = $data['discount_amount'] ?? 0;
        $total = $subtotal - $discountAmount;

        $order = Order::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $data['client_id'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'fee_amount' => 0,
            'total_amount' => $total,
            'status' => $isPresential ? Order::STATUS_DELIVERED : Order::STATUS_PENDING,
            'channel' => $channel,
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'confirmed_at' => $isPresential ? now() : null,
            'shipped_at' => $isPresential ? now() : null,
            'delivered_at' => $isPresential ? now() : null,
            'delivery_zip' => $data['delivery_zip'] ?? null,
            'delivery_street' => $data['delivery_street'] ?? null,
            'delivery_number' => $data['delivery_number'] ?? null,
            'delivery_complement' => $data['delivery_complement'] ?? null,
            'delivery_neighborhood' => $data['delivery_neighborhood'] ?? null,
            'delivery_city' => $data['delivery_city'] ?? null,
            'delivery_state' => $data['delivery_state'] ?? null,
            'delivery_latitude' => $data['delivery_latitude'] ?? null,
            'delivery_longitude' => $data['delivery_longitude'] ?? null,
        ]);

        $costsByProductId = Product::whereIn('id', collect($items)->pluck('product_id'))->pluck('cost', 'id');

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
                'cost' => $costsByProductId->get($item['product_id']),
                'discount_percent' => 0,
                'discount_amount' => 0,
                'total_amount' => $itemTotal,
            ]);

            if ($isPresential) {
                Product::decrementStockSafely($item['product_id'], $quantity);
            }
        }

        return $order;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
