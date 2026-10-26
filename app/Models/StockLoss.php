<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockLoss extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (StockLoss $loss) {
            if (! $loss->uuid) {
                $loss->uuid = (string) Str::uuid();
            }
        });

        static::created(function (StockLoss $loss) {
            $loss->product()->decrement('quantity', $loss->quantity);
        });

        // Deleting a loss record (e.g. registered by mistake) must give the
        // stock back, or the product ends up permanently short by the
        // mistaken amount.
        static::deleted(function (StockLoss $loss) {
            $loss->product()->increment('quantity', $loss->quantity);
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'product_id',
        'quantity',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
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
