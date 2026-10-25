<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'cost',
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
        'cost' => 'decimal:2',
    ];

    /**
     * Acquisition cost is admin-only (dashboard margin calculations) and must
     * never reach the client marketplace or API, which serialize raw Product
     * models.
     */
    protected $hidden = ['cost'];

    public function hasCost(): bool
    {
        return $this->cost !== null && (float) $this->cost > 0;
    }

    public function hasSufficientStock(int $quantity): bool
    {
        return (int) $this->quantity >= $quantity;
    }

    /**
     * Decrements stock atomically and never lets it go below zero, even
     * under a race between two simultaneous sales of the same product.
     */
    public static function decrementStockSafely(int $productId, int $quantity): void
    {
        static::whereKey($productId)->update([
            'quantity' => DB::raw('GREATEST(quantity - '.$quantity.', 0)'),
        ]);
    }

    public static function restoreStock(int $productId, int $quantity): void
    {
        static::whereKey($productId)->increment('quantity', $quantity);
    }

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
