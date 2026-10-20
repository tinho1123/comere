<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderMessage extends Model
{
    use HasFactory;

    const SENDER_CLIENT = 'client';

    const SENDER_COMPANY = 'company';

    protected $fillable = [
        'uuid',
        'company_id',
        'order_id',
        'sender_type',
        'sender_id',
        'author_name',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderMessage $message) {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
