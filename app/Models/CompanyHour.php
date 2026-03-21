<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHour extends Model
{
    protected $fillable = ['company_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    protected $casts = ['is_closed' => 'boolean'];

    public const DAY_NAMES = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
