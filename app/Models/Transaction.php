<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (auth()->check() && ! $transaction->company_id) {
                // Para painel admin, obter empresa do usuário logado
                $user = auth()->user();
                if ($user instanceof User) {
                    $transaction->company_id = $user->companies->first()->id;
                }
            }
            if (! $transaction->uuid) {
                $transaction->uuid = Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'product_id',
        'fees_id',
        'name',
        'description',
        'amount',
        'discounts',
        'fees',
        'active',
        'total_amount',
        'quantity',
        'image',
        'isCool',
        'category_name',
        'category_id',
        'client_name',
        'client_id',
        'type',
        'payment_method',
    ];

    protected $casts = [
        'active' => 'boolean',
        'isCool' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
