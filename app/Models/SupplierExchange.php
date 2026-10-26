<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierExchange extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_SENT = 'sent';

    const STATUS_EXCHANGED = 'exchanged';

    const STATUS_REFUSED = 'refused';

    protected static function booted()
    {
        static::creating(function (SupplierExchange $exchange) {
            if (! $exchange->uuid) {
                $exchange->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'product_id',
        'quantity',
        'reason',
        'status',
        'notes',
        'resolved_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
