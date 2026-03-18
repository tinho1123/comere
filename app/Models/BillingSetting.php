<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'fee_per_transaction',
        'payment_day',
    ];

    protected $casts = [
        'fee_per_transaction' => 'decimal:2',
        'payment_day' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
