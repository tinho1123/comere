<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
