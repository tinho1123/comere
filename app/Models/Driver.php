<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Driver extends Model
{
    use HasFactory;

    const VEHICLE_MOTOBOY = 'motoboy';

    const VEHICLE_CAR = 'car';

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'vehicle_type',
        'phone',
        'cpf',
        'delivery_fee',
        'is_active',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Driver $driver) {
            if (empty($driver->uuid)) {
                $driver->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('status', Delivery::STATUS_DISPATCHED);
    }
}
