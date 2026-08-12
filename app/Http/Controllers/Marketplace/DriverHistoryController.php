<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use Inertia\Inertia;

class DriverHistoryController extends Controller
{
    public function show()
    {
        /** @var Driver $driver */
        $driver = auth('driver')->user();

        $completed = $driver->deliveries()
            ->where('status', Delivery::STATUS_DELIVERED)
            ->with(['order', 'company'])
            ->orderByDesc('delivered_at')
            ->get();

        return Inertia::render('Driver/History', [
            'deliveries' => $completed->map(fn (Delivery $delivery) => [
                'id' => $delivery->id,
                'company_name' => $delivery->company->name,
                'order_short_id' => strtoupper(substr($delivery->order->uuid, 0, 8)),
                'driver_fee' => $delivery->driver_fee,
                'delivered_at' => $delivery->delivered_at?->format('d/m/Y H:i'),
            ])->values(),
            'totals' => [
                'lifetime' => $completed->sum('driver_fee'),
                'today' => $completed->filter(fn (Delivery $d) => $d->delivered_at?->isToday())->sum('driver_fee'),
                'this_week' => $completed->filter(fn (Delivery $d) => $d->delivered_at?->isCurrentWeek())->sum('driver_fee'),
                'deliveries_count' => $completed->count(),
            ],
        ]);
    }
}
