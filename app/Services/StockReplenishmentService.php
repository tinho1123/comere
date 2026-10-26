<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Computes how much of a product to order from its supplier, from recent
 * sale velocity (order_items on delivered orders — covers marketplace,
 * presential and table-session sales, since closing a table session
 * creates a delivered Order) adjusted for the product's seasonality
 * within the month, projected until the supplier's next delivery.
 */
class StockReplenishmentService
{
    const MIN_HISTORY_DAYS = 14;

    const PERIOD_DAYS = 30;

    const SEASONALITY_WINDOW_DAYS = 90;

    public function suggestionForProduct(Product $product, int $periodDays = self::PERIOD_DAYS): array
    {
        $historyDays = $product->created_at ? (int) $product->created_at->diffInDays(now()) : 0;
        $hasEnoughHistory = $historyDays >= self::MIN_HISTORY_DAYS;

        if (! $hasEnoughHistory) {
            return [
                'has_enough_history' => false,
                'velocity' => null,
                'days_to_cover' => null,
                'suggested_quantity' => null,
                'label' => 'Histórico insuficiente',
                'season_label' => '—',
            ];
        }

        $periodTotal = $this->soldQuantity($product->id, now()->subDays($periodDays));
        $velocity = $periodDays > 0 ? $periodTotal / $periodDays : 0.0;

        $supplier = $product->supplier;
        $daysToCover = null;

        if ($supplier && $supplier->next_visit_at) {
            $daysUntilVisit = max(0, (int) now()->startOfDay()->diffInDays($supplier->next_visit_at->copy()->startOfDay(), false));
            $daysToCover = $daysUntilVisit + (int) ($supplier->lead_time_days ?? 0);
        }

        [$adjustedVelocity, $seasonLabel] = $this->applySeasonality($product->id, $velocity, $daysToCover);

        if ($daysToCover === null) {
            return [
                'has_enough_history' => true,
                'velocity' => round($velocity, 1),
                'days_to_cover' => null,
                'suggested_quantity' => null,
                'label' => 'Defina a próxima visita do fornecedor',
                'season_label' => $seasonLabel,
            ];
        }

        $target = $adjustedVelocity * $daysToCover + ($product->min_stock ?? 0);
        $needed = $target - $product->quantity;

        $suggestedQuantity = 0;

        if ($needed > 0) {
            $packSize = max(1, (int) ($product->purchase_unit_size ?? 1));
            $suggestedQuantity = (int) (ceil($needed / $packSize) * $packSize);
        }

        return [
            'has_enough_history' => true,
            'velocity' => round($velocity, 1),
            'days_to_cover' => $daysToCover,
            'suggested_quantity' => $suggestedQuantity,
            'label' => $suggestedQuantity > 0 ? "Pedir {$suggestedQuantity} un" : 'OK, não precisa',
            'season_label' => $seasonLabel,
        ];
    }

    private function soldQuantity(int $productId, Carbon $from): int
    {
        return (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->where('order_items.created_at', '>=', $from)
            ->sum('order_items.quantity');
    }

    /**
     * Compares average daily sales in the first vs second half of the
     * month (over SEASONALITY_WINDOW_DAYS) and, if the date the product
     * needs to last until falls clearly on one side, uses that half's
     * velocity instead of the flat period average.
     */
    private function applySeasonality(int $productId, float $overallVelocity, ?int $daysToCover): array
    {
        if ($daysToCover === null) {
            return [$overallVelocity, 'Estável'];
        }

        $targetHalf = now()->addDays($daysToCover)->day <= 15 ? 'first' : 'second';

        $from = now()->subDays(self::SEASONALITY_WINDOW_DAYS);
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->where('order_items.created_at', '>=', $from)
            ->get(['order_items.created_at', 'order_items.quantity']);

        $firstHalfQty = 0;
        $secondHalfQty = 0;

        foreach ($rows as $row) {
            if (Carbon::parse($row->created_at)->day <= 15) {
                $firstHalfQty += $row->quantity;
            } else {
                $secondHalfQty += $row->quantity;
            }
        }

        $firstHalfDays = 0;
        $secondHalfDays = 0;

        for ($i = 0; $i < self::SEASONALITY_WINDOW_DAYS; $i++) {
            if (now()->copy()->subDays($i)->day <= 15) {
                $firstHalfDays++;
            } else {
                $secondHalfDays++;
            }
        }

        $firstHalfVelocity = $firstHalfDays > 0 ? $firstHalfQty / $firstHalfDays : 0.0;
        $secondHalfVelocity = $secondHalfDays > 0 ? $secondHalfQty / $secondHalfDays : 0.0;
        $windowVelocity = ($firstHalfQty + $secondHalfQty) / self::SEASONALITY_WINDOW_DAYS;

        if ($windowVelocity <= 0) {
            return [$overallVelocity, 'Estável'];
        }

        $halfVelocity = $targetHalf === 'first' ? $firstHalfVelocity : $secondHalfVelocity;

        if ($halfVelocity <= 0) {
            return [$overallVelocity, 'Estável'];
        }

        $multiplier = $halfVelocity / $windowVelocity;
        $halfName = $targetHalf === 'first' ? 'Início do mês' : 'Fim do mês';

        if ($multiplier >= 1.15) {
            return [$halfVelocity, $halfName.' +'.round(($multiplier - 1) * 100).'%'];
        }

        if ($multiplier <= 0.85) {
            return [$halfVelocity, $halfName.' -'.round((1 - $multiplier) * 100).'%'];
        }

        return [$overallVelocity, 'Estável'];
    }
}
