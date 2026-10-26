<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierOrder extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_RECEIVED = 'received';

    const STATUS_CANCELLED = 'cancelled';

    protected static function booted()
    {
        static::creating(function (SupplierOrder $order) {
            if (! $order->uuid) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'supplier_id',
        'product_id',
        'quantity_suggested',
        'quantity_ordered',
        'requested_at',
        'expected_delivery_at',
        'received_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity_suggested' => 'integer',
        'quantity_ordered' => 'integer',
        'requested_at' => 'date',
        'expected_delivery_at' => 'date',
        'received_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function markReceived(): void
    {
        $this->product()->increment('quantity', $this->quantity_ordered);
        $this->update([
            'status' => self::STATUS_RECEIVED,
            'received_at' => now(),
        ]);
    }
}
