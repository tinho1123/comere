<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Filament\Panel\Concerns\HasTenancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Collection;

class Client extends AuthenticatableUser implements FilamentUser, HasTenants
{
    use HasFactory, HasTenancy;

    protected $fillable = [
        'uuid',
        'email',
        'password',
        'document_type',
        'document_number',
        'name',
        'phone',
        'active',
        'last_login_at',
        'login_attempts',
        'locked_until',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'preferences' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Relacionamento N:N com Company via tabela pivot client_company
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function defaultAddress(): HasMany
    {
        return $this->hasMany(ClientAddress::class)->where('is_default', true);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'client_company')
            ->withPivot('is_active')
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    /**
     * Implementação de HasTenants para Filament
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->companies()->get();
    }

    /**
     * Verificar se o cliente pode acessar um tenant específico
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()
            ->where('companies.id', $tenant->id)
            ->exists();
    }

    /**
     * Verificar se o cliente pode acessar o painel Filament
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'client' && $this->active;
    }

    /**
     * Retornar o nome do cliente
     */
    public function getName(): string
    {
        return $this->name ?? $this->email ?? 'Cliente';
    }

    /**
     * Validar senha e resetar tentativas de login
     */
    public function validateLoginAttempts(): bool
    {
        // Verificar se a conta está bloqueada
        if ($this->locked_until && now()->lt($this->locked_until)) {
            return false;
        }

        return true;
    }

    /**
     * Incrementar tentativas de login falhadas
     */
    public function incrementLoginAttempts(): void
    {
        $attempts = ($this->login_attempts ?? 0) + 1;

        // Bloquear após 5 tentativas por 30 minutos
        if ($attempts >= 5) {
            $this->update([
                'login_attempts' => 0,
                'locked_until' => now()->addMinutes(30),
            ]);
        } else {
            $this->update(['login_attempts' => $attempts]);
        }
    }

    /**
     * Resetar tentativas de login após sucesso
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);
    }
}
