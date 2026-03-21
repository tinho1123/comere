<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'company_type_id',
        'logo_path',
        'banner_path',
        'foundation_date',
        'rating',
        'delivery_time',
        'is_promoted',
        'active',
        'address_zip',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'foundation_date' => 'date',
        'rating' => 'decimal:1',
        'active' => 'boolean',
        'is_promoted' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function companyType(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(CompanyRating::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(CompanyHour::class)->orderBy('day_of_week');
    }

    public function isOpenNow(): bool
    {
        $now = now();
        $dayOfWeek = (int) $now->format('w'); // 0=Dom, 6=Sáb
        $currentTime = $now->format('H:i:s');

        $schedule = $this->hours->firstWhere('day_of_week', $dayOfWeek);

        if (! $schedule || $schedule->is_closed || ! $schedule->opens_at || ! $schedule->closes_at) {
            return false;
        }

        // Suporte a horários que cruzam meia-noite (ex: 22:00 → 02:00)
        if ($schedule->closes_at < $schedule->opens_at) {
            return $currentTime >= $schedule->opens_at || $currentTime <= $schedule->closes_at;
        }

        return $currentTime >= $schedule->opens_at && $currentTime <= $schedule->closes_at;
    }

    public function recalculateRating(): void
    {
        $avg = $this->ratings()->avg('rating');
        $this->update(['rating' => $avg ? round($avg, 1) : null]);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'companies_users');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function productsCategories(): HasMany
    {
        return $this->hasMany(ProductsCategories::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function favoredTransactions(): HasMany
    {
        return $this->hasMany(FavoredTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryFeeRanges(): HasMany
    {
        return $this->hasMany(DeliveryFeeRange::class)->orderBy('max_km');
    }

    public function billingSetting(): HasOne
    {
        return $this->hasOne(BillingSetting::class);
    }

    public function billingCycles(): HasMany
    {
        return $this->hasMany(BillingCycle::class)->orderByDesc('period_start');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey(): int|string|null
    {
        return $this->id;
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
