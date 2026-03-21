<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TableSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'table_id',
        'client_id',
        'guest_name',
        'device_token',
        'status',
        'payment_method',
        'notes',
        'subtotal',
        'surcharge_amount',
        'total_amount',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TableSessionItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getClientDisplayNameAttribute(): string
    {
        if ($this->client) {
            return $this->client->name;
        }

        return $this->guest_name ?? '—';
    }

    public function close(?string $paymentMethod = null): void
    {
        $items = $this->items()->with('product')->get();

        foreach ($items as $item) {
            if ($item->product) {
                $item->product->decrement('quantity', $item->quantity);
            }
        }

        $subtotal = (float) $items->sum('total_amount');
        $surchargeAmount = $this->calculateSurcharge($subtotal, $paymentMethod, $items);
        $total = $subtotal + $surchargeAmount;

        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'subtotal' => $subtotal,
            'surcharge_amount' => $surchargeAmount,
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
        ]);

        $this->createOrder($items, $subtotal, $surchargeAmount, $total, $paymentMethod);
    }

    public function calculateSurcharge(float $subtotal, ?string $paymentMethod, ?Collection $loadedItems = null): float
    {
        if (! $paymentMethod) {
            return 0.0;
        }

        $items = $loadedItems ?? $this->items()->with('product')->get();

        $total = 0.0;
        foreach ($items as $item) {
            if ($item->product) {
                $total += $item->product->getSurchargeFor($paymentMethod, (float) $item->total_amount, $item->quantity);
            }
        }

        return round($total, 2);
    }

    public function closeAsFavored(string $pricingMode): void
    {
        $items = $this->items()->with('product')->get();

        foreach ($items as $item) {
            if ($item->product) {
                $item->product->decrement('quantity', $item->quantity);
            }
        }

        $subtotal = (float) $items->sum(function ($item) use ($pricingMode) {
            if ($pricingMode === 'favored' && $item->product && $item->product->favored_price) {
                return (float) $item->product->favored_price * $item->quantity;
            }

            return (float) $item->total_amount;
        });

        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'subtotal' => $subtotal,
            'surcharge_amount' => 0,
            'total_amount' => $subtotal,
            'payment_method' => 'favored',
        ]);

        $order = $this->createOrder($items, $subtotal, 0, $subtotal, 'favored');

        $clientName = ($this->client_display_name !== '—')
            ? $this->client_display_name
            : ($this->guest_name ?? 'Mesa');

        foreach ($items as $item) {
            $useFavoredPrice = $pricingMode === 'favored'
                && $item->product
                && $item->product->favored_price;

            $unitPrice = $useFavoredPrice
                ? (float) $item->product->favored_price
                : (float) $item->unit_price;

            $itemTotal = $unitPrice * $item->quantity;

            FavoredTransaction::create([
                'company_id' => $this->company_id,
                'client_id' => null,
                'client_name' => $clientName,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'amount' => $unitPrice,
                'total_amount' => $itemTotal,
                'favored_total' => $itemTotal,
                'favored_paid_amount' => 0,
                'order_id' => $order->id,
                'due_date' => now()->addDays(30),
                'active' => true,
            ]);
        }
    }

    protected function createOrder(Collection $items, float $subtotal, float $surchargeAmount, float $total, ?string $paymentMethod): Order
    {
        $this->loadMissing('table');

        $tableName = $this->table?->name ?? 'Mesa';
        $guest = $this->client_display_name !== '—' ? ' — '.$this->client_display_name : '';

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company_id,
            'client_id' => $this->client_id,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'fee_amount' => $surchargeAmount,
            'total_amount' => $total,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_PRESENTIAL,
            'payment_method' => $paymentMethod,
            'notes' => $tableName.$guest,
            'confirmed_at' => now(),
            'delivered_at' => now(),
        ]);

        foreach ($items as $item) {
            OrderItem::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'total_amount' => $item->total_amount,
            ]);
        }

        return $order;
    }
}
