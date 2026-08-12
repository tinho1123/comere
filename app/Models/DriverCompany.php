<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverCompany extends Model
{
    protected $table = 'driver_company';

    protected $fillable = [
        'driver_id',
        'company_id',
        'status',
        'delivery_fee',
        'responded_at',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
