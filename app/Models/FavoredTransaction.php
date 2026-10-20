<?php

namespace App\Models;

use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FavoredTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'product_id',
        'name',
        'description',
        'amount',
        'discounts',
        'total_amount',
        'favored_total',
        'favored_paid_amount',
        'quantity',
        'image',
        'active',
        'due_date',
        'category_name',
        'category_id',
        'client_name',
        'order_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discounts' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'favored_total' => 'decimal:2',
        'favored_paid_amount' => 'decimal:2',
        'quantity' => 'integer',
        'active' => 'boolean',
        'due_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductsCategories::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (FavoredTransaction $model) {
            if (is_null($model->uuid)) {
                $model->uuid = Str::uuid();
            }

            // If amount or total_amount are not provided, default them
            // to the favored_total when available, or to 0.
            if (is_null($model->amount)) {
                $model->amount = $model->favored_total ?? 0;
            }

            if (is_null($model->total_amount)) {
                $model->total_amount = $model->favored_total ?? $model->amount ?? 0;
            }

            if (is_null($model->favored_total)) {
                $model->favored_total = $model->total_amount ?? $model->amount ?? 0;
            }

            if (is_null($model->favored_paid_amount)) {
                $model->favored_paid_amount = 0;
            }
        });

        static::updated(function (FavoredTransaction $model) {
            if (! $model->wasChanged('favored_paid_amount')) {
                return;
            }

            if ((float) $model->favored_paid_amount <= (float) $model->getOriginal('favored_paid_amount')) {
                return;
            }

            $paidNow = (float) $model->favored_paid_amount - (float) $model->getOriginal('favored_paid_amount');
            $clientName = $model->client_name ?? $model->client?->name ?? 'Cliente';
            $companyUuid = $model->company?->uuid ?? $model->company()->value('uuid');

            try {
                app(PushNotificationService::class)->notifyCompany(
                    $model->company_id,
                    '💰 Pagamento de fiado registrado',
                    $clientName.' pagou R$ '.number_format($paidNow, 2, ',', '.').' de "'.$model->name.'".',
                    $companyUuid ? "/admin/{$companyUuid}/favored-transactions" : '/admin'
                );
            } catch (\Throwable) {
                // Nunca falhar o registro de pagamento por causa de erro na notificação
            }
        });
    }

    public function getRemainingBalance(): float
    {
        return $this->favored_total - $this->favored_paid_amount;
    }

    public function isFullyPaid(): bool
    {
        return $this->favored_paid_amount >= $this->favored_total;
    }
}
