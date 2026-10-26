<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TableSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_session_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_amount',
        'is_delivered',
        'delivered_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_delivered' => 'boolean',
        'delivered_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        // O estoque é reservado no momento em que o item entra na mesa
        // (ainda aberta), não só quando a mesa é fechada — assim ele some
        // da lista de disponíveis pra outros clientes e pro marketplace
        // imediatamente. Ver TableSession::close()/closeAsFavored().
        static::creating(function (TableSessionItem $item) {
            if (! $item->product_id) {
                return;
            }

            // Decremento condicional numa única query atômica (UPDATE ...
            // WHERE quantity >= n): evita a corrida em que duas reservas
            // simultâneas leem o mesmo saldo e as duas passam.
            $reserved = Product::whereKey($item->product_id)
                ->where('quantity', '>=', $item->quantity)
                ->update(['quantity' => DB::raw("quantity - {$item->quantity}")]);

            if (! $reserved) {
                $product = Product::find($item->product_id);

                throw new \RuntimeException(
                    "Estoque insuficiente para \"{$product?->name}\". Disponível: {$product?->quantity}."
                );
            }
        });

        static::deleted(function (TableSessionItem $item) {
            if ($item->product_id) {
                Product::restoreStock($item->product_id, $item->quantity);
            }
        });
    }
}
