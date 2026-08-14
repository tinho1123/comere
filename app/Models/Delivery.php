<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Delivery extends Model
{
    use HasFactory;

    const STATUS_DISPATCHED = 'dispatched';

    const STATUS_DELIVERED = 'delivered';

    const STATUS_FAILED = 'failed';

    const FAILURE_REASONS = [
        'customer_unavailable' => 'Cliente não atende',
        'wrong_address' => 'Endereço não encontrado',
        'customer_refused' => 'Cliente recusou o pedido',
        'other' => 'Outro motivo',
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'order_id',
        'driver_id',
        'status',
        'driver_fee',
        'is_paid',
        'dispatched_at',
        'picked_up_at',
        'delivered_at',
        'notes',
        'failure_reason',
        'pickup_code',
        'payment_confirmation_code',
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
        'picked_up_at' => 'datetime',
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
            if (empty($delivery->pickup_code)) {
                $delivery->pickup_code = self::generateCode();
            }
            if (empty($delivery->payment_confirmation_code)) {
                $delivery->payment_confirmation_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function trackingUrl(): string
    {
        return route('delivery.tracking.show', $this->tracking_token);
    }

    public function isPickedUp(): bool
    {
        return $this->picked_up_at !== null;
    }

    public function failureReasonLabel(): ?string
    {
        if (! $this->failure_reason) {
            return null;
        }

        return self::FAILURE_REASONS[$this->failure_reason] ?? $this->failure_reason;
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

    public function feedback(): HasOne
    {
        return $this->hasOne(DeliveryFeedback::class);
    }
}
