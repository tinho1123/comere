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
        // CASE WHEN instead of GREATEST() — the latter doesn't exist on
        // SQLite, which the test suite runs against.
        static::whereKey($productId)->update([
            'quantity' => DB::raw("CASE WHEN quantity >= {$quantity} THEN quantity - {$quantity} ELSE 0 END"),
        ]);
    }

    public static function restoreStock(int $productId, int $quantity): void
    {
        static::whereKey($productId)->increment('quantity', $quantity);
    }

    /**
     * Checks stock for a list of order items (as submitted by a checkout
     * form/request), summing quantities per product first — a request
     * with the same product split across two rows must be checked against
     * its combined quantity, not each row against the full stock on its
     * own. Scoping to $companyId also keeps the error message (product
     * name + exact stock) from leaking a product that belongs to another
     * tenant.
     */
    public static function findInsufficientStockMessage(array $items, ?int $companyId = null): ?string
    {
        $quantitiesByProduct = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;

            if (! $productId) {
                continue;
            }

            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + (int) ($item['quantity'] ?? 0);
        }

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $product = $companyId
                ? static::where('company_id', $companyId)->find($productId)
                : static::find($productId);

            if ($product && ! $product->hasSufficientStock($quantity)) {
                return "Estoque insuficiente para \"{$product->name}\". Disponível: {$product->quantity}.";
            }
        }

        return null;
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
