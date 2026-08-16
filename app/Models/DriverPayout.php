<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DriverPayout extends Model
{
    use HasFactory;

    const METHOD_PIX = 'pix';

    const METHOD_CASH = 'cash';

    const METHOD_TRANSFER = 'transfer';

    protected $fillable = [
        'uuid',
        'company_id',
        'driver_id',
        'total_amount',
        'method',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DriverPayout $payout) {
            if (empty($payout->uuid)) {
                $payout->uuid = (string) Str::uuid();
            }
        });
    }

    public static function methodOptions(): array
    {
        return [
            self::METHOD_PIX => 'Pix',
            self::METHOD_CASH => 'Dinheiro',
            self::METHOD_TRANSFER => 'Transferência',
        ];
    }

    public function methodLabel(): string
    {
        return self::methodOptions()[$this->method] ?? $this->method;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
