<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'table_id',
        'client_id',
        'guest_name',
        'device_token',
        'status',
        'notes',
        'total_amount',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TableSessionItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getClientDisplayNameAttribute(): string
    {
        if ($this->client) {
            return $this->client->name;
        }

        return $this->guest_name ?? '—';
    }

    public function close(): void
    {
        foreach ($this->items()->with('product')->get() as $item) {
            if ($item->product) {
                $item->product->decrement('quantity', $item->quantity);
            }
        }

        $total = $this->items()->sum('total_amount');

        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'total_amount' => $total,
        ]);
    }
}
