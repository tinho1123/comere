<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSurcharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'payment_method',
        'type',
        'amount',
        'active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

    public function calculate(float $subtotal): float
    {
        if ($this->type === 'percent') {
            return round($subtotal * ($this->amount / 100), 2);
        }

        return (float) $this->amount;
    }

    public function label(): string
    {
        if ($this->type === 'percent') {
            return '+'.number_format($this->amount, 2, ',', '.').'%';
        }

        return '+R$ '.number_format($this->amount, 2, ',', '.');
    }
}
