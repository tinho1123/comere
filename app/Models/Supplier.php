<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory;

    const FREQUENCY_WEEKLY = 'weekly';

    const FREQUENCY_MONTHLY = 'monthly';

    protected static function booted()
    {
        static::creating(function (Supplier $supplier) {
            if (! $supplier->uuid) {
                $supplier->uuid = (string) Str::uuid();
            }

            if (! $supplier->next_visit_at && $supplier->last_visit_at) {
                $supplier->next_visit_at = $supplier->calculateNextVisit();
            }

            if ($supplier->next_visit_overridden === null) {
                $supplier->next_visit_overridden = false;
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'visit_frequency',
        'lead_time_days',
        'last_visit_at',
        'next_visit_at',
        'next_visit_overridden',
    ];

    protected $casts = [
        'last_visit_at' => 'date',
        'next_visit_at' => 'date',
        'next_visit_overridden' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function calculateNextVisit(): ?Carbon
    {
        if (! $this->last_visit_at) {
            return null;
        }

        return $this->visit_frequency === self::FREQUENCY_MONTHLY
            ? $this->last_visit_at->copy()->addMonth()
            : $this->last_visit_at->copy()->addWeek();
    }

    /**
     * Records that the supplier's representative actually visited: moves
     * last_visit_at forward and recalculates next_visit_at unless it has
     * since been overridden manually (e.g. holiday, route or team change).
     */
    public function markVisited(\DateTimeInterface|string|null $visitedAt = null): void
    {
        $this->last_visit_at = $visitedAt ? Carbon::parse($visitedAt) : now();
        $this->next_visit_overridden = false;
        $this->next_visit_at = $this->calculateNextVisit();
        $this->save();
    }
}
