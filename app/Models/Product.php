<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function ($product) {
            if (! $product->uuid) {
                $product->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'name',
        'description',
        'amount',
        'discounts',
        'total_amount',
        'quantity',
        'image',
        'isCool',
        'is_for_favored',
        'favored_price',
        'is_marketplace',
        'payment_surcharges',
        'category_id',
        'subcategory_id',
        'active',
        'uuid',
    ];

    protected $casts = [
        'active' => 'boolean',
        'isCool' => 'boolean',
        'is_for_favored' => 'boolean',
        'is_marketplace' => 'boolean',
        'payment_surcharges' => 'array',
    ];

    public function getSurchargeFor(string $paymentMethod, float $itemTotal, int $quantity = 1): float
    {
        $surcharges = $this->payment_surcharges ?? [];
        $config = $surcharges[$paymentMethod] ?? null;

        if (! $config || empty($config['amount'])) {
            return 0.0;
        }

        if ($config['type'] === 'percent') {
            return round($itemTotal * ($config['amount'] / 100), 2);
        }

        return round((float) $config['amount'] * $quantity, 2);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductsCategories::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
