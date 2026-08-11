<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    const TYPE_PERCENT = 'percent';

    const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'uuid',
        'company_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isValidFor(float $orderAmount): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->min_order_amount !== null && $orderAmount < (float) $this->min_order_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->discount_type === self::TYPE_FIXED) {
            return min((float) $this->discount_value, $orderAmount);
        }

        return round($orderAmount * ((float) $this->discount_value / 100), 2);
    }
}
