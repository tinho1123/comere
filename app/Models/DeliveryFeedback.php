<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeliveryFeedback extends Model
{
    use HasFactory;

    protected $table = 'delivery_feedbacks';

    const RATING_GOOD = 'good';

    const RATING_BAD = 'bad';

    protected $fillable = [
        'uuid',
        'company_id',
        'delivery_id',
        'driver_id',
        'rating',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeliveryFeedback $feedback) {
            if (empty($feedback->uuid)) {
                $feedback->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
