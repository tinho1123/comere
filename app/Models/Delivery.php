<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Delivery extends Model
{
    use HasFactory;

    const STATUS_DISPATCHED = 'dispatched';

    const STATUS_DELIVERED = 'delivered';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'company_id',
        'order_id',
        'driver_id',
        'status',
        'driver_fee',
        'is_paid',
        'dispatched_at',
        'delivered_at',
        'notes',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'payment_collected',
        'payment_collected_at',
    ];

    protected $casts = [
        'driver_fee' => 'decimal:2',
        'is_paid' => 'boolean',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'location_updated_at' => 'datetime',
        'payment_collected' => 'boolean',
        'payment_collected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery) {
            if (empty($delivery->uuid)) {
                $delivery->uuid = (string) Str::uuid();
            }
            if (empty($delivery->tracking_token)) {
                $delivery->tracking_token = Str::random(40);
            }
        });
    }

    public function trackingUrl(): string
    {
        return route('delivery.tracking.show', $this->tracking_token);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
