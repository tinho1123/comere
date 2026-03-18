<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_SHIPPED = 'shipped';

    const STATUS_DELIVERED = 'delivered';

    const STATUS_CANCELLED = 'cancelled';

    const CHANNEL_ONLINE = 'online';

    const CHANNEL_PRESENTIAL = 'presential';

    const PAYMENT_CASH = 'cash';

    const PAYMENT_DEBIT = 'debit';

    const PAYMENT_CREDIT = 'credit';

    const PAYMENT_PIX = 'pix';

    public static function paymentOptions(): array
    {
        return [
            self::PAYMENT_CASH => 'Dinheiro',
            self::PAYMENT_DEBIT => 'Cartão de Débito',
            self::PAYMENT_CREDIT => 'Cartão de Crédito',
            self::PAYMENT_PIX => 'Pix',
        ];
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'subtotal',
        'discount_amount',
        'fee_amount',
        'total_amount',
        'status',
        'channel',
        'payment_method',
        'notes',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the Company that owns this order.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope: get processing orders.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope: get shipped orders.
     */
    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    /**
     * Scope: get delivered orders.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Check if the order can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the order can be shipped.
     */
    public function canBeShipped(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the order can be delivered.
     */
    public function canBeDelivered(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    /**
     * Approve the order.
     */
    public function approve(): void
    {
        if ($this->canBeApproved()) {
            $this->status = self::STATUS_PROCESSING;
            $this->confirmed_at = now();
            $this->save();
        }
    }

    /**
     * Ship the order.
     */
    public function ship(): void
    {
        if ($this->canBeShipped()) {
            $this->status = self::STATUS_SHIPPED;
            $this->shipped_at = now();
            $this->save();

            foreach ($this->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $item->product->decrement('quantity', $item->quantity);
                }
            }
        }
    }

    /**
     * Deliver the order.
     */
    public function deliver(): void
    {
        if ($this->canBeDelivered()) {
            $this->status = self::STATUS_DELIVERED;
            $this->delivered_at = now();
            $this->save();
        }
    }

    /**
     * Recalculate total amount.
     */
    public function recalculateTotal(): self
    {
        $subtotal = $this->items()->sum('total_amount');
        $this->subtotal = $subtotal;
        $this->total_amount = $subtotal - $this->discount_amount + $this->fee_amount;
        $this->save();

        return $this;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
