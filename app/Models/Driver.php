<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Str;

class Driver extends AuthenticatableUser
{
    use HasFactory;

    const VEHICLE_MOTOBOY = 'motoboy';

    const VEHICLE_CAR = 'car';

    const LINK_PENDING = 'pending';

    const LINK_ACCEPTED = 'accepted';

    const LINK_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'name',
        'vehicle_type',
        'phone',
        'cpf',
        'password',
        'is_active',
        'is_online',
        'last_latitude',
        'last_longitude',
        'last_location_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
        'last_location_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Driver $driver) {
            if (empty($driver->uuid)) {
                $driver->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Empresas vinculadas a este motorista, com o status do vínculo
     * (a tabela pivot driver_company guarda o status: pending/accepted/rejected).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'driver_company')
            ->withPivot(['id', 'status', 'delivery_fee', 'responded_at'])
            ->withTimestamps();
    }

    public function acceptedCompanies(): BelongsToMany
    {
        return $this->companies()->wherePivot('status', self::LINK_ACCEPTED);
    }

    public function pendingInvites(): BelongsToMany
    {
        return $this->companies()->wherePivot('status', self::LINK_PENDING);
    }

    public function isLinkedTo(Company $company): bool
    {
        return $this->acceptedCompanies()->where('companies.id', $company->id)->exists();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('status', Delivery::STATUS_DISPATCHED);
    }

    /**
     * O GPS é obrigatório pra ficar online: só consideramos a localização
     * válida se ela foi reportada há pouco tempo.
     */
    public function hasFreshLocation(int $maxMinutes = 3): bool
    {
        return $this->last_location_at !== null
            && $this->last_location_at->gt(now()->subMinutes($maxMinutes));
    }
}
