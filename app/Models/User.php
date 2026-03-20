<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'uuid',
        'is_master',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_master' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $model) {
            if (is_null($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function isMaster(): bool
    {
        return (bool) $this->is_master;
    }

    public function company(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'companies_users');
    }

    public function companies(): BelongsToMany
    {
        return $this->company();
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isMaster()) {
            return Company::all();
        }

        return $this->companies()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isMaster()) {
            return true;
        }

        return $this->companies()->wherePivot('company_id', $tenant->id)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'master') {
            return $this->isMaster();
        }

        return true;
    }
}
