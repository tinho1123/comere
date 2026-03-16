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
        'category_id',
        'active',
        'uuid',
    ];

    protected $casts = [
        'active' => 'boolean',
        'isCool' => 'boolean',
        'is_for_favored' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductsCategories::class, 'category_id');
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
