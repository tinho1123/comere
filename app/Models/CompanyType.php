<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CompanyType extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'icon'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CompanyType $model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
