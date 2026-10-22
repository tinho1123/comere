<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'notes',
        'unit_price',
        'cost',
        'discount_percent',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Custo é dado financeiro só do admin (dashboard/relatório de margem) —
     * nunca deve aparecer em resposta de API pro cliente/marketplace.
     */
    protected $hidden = ['cost'];

    /**
     * Get the Order that owns this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the Product that owns this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Calculate total amount based on quantity and price.
     */
    public function calculateTotal(): self
    {
        $subtotal = $this->quantity * $this->unit_price;
        $this->discount_amount = $subtotal * ($this->discount_percent / 100);
        $this->total_amount = $subtotal - $this->discount_amount;

        return $this;
    }
}
