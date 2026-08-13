<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DriverDashboardController extends Controller
{
    public function show()
    {
        /** @var Driver $driver */
        $driver = auth('driver')->user();

        return Inertia::render('Driver/Dashboard', [
            'driver' => $this->driverPayload($driver),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'pendingInvites' => $driver->pendingInvites()->get()->map(fn ($company) => [
                'pivot_id' => $company->pivot->id,
                'name' => $company->name,
                'delivery_fee' => $company->pivot->delivery_fee,
            ]),
            'acceptedCompanies' => $driver->acceptedCompanies()->get()->map(fn ($company) => [
                'name' => $company->name,
                'delivery_fee' => $company->pivot->delivery_fee,
            ]),
            'activeDeliveries' => $this->activeDeliveriesPayload($driver),
            'todayStats' => $this->todayStatsPayload($driver),
        ]);
    }

    public function poll()
    {
        /** @var Driver $driver */
        $driver = auth('driver')->user();

        return response()->json([
            'is_online' => $driver->is_online,
            'has_location' => $driver->hasFreshLocation(),
            'active_deliveries' => $this->activeDeliveriesPayload($driver),
            'pending_invites_count' => $driver->pendingInvites()->count(),
            'today_stats' => $this->todayStatsPayload($driver),
        ]);
    }

    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        /** @var Driver $driver */
        $driver = auth('driver')->user();
        $driver->update([
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_location_at' => now(),
        ]);

        return response()->json(['success' => true, 'has_location' => true]);
    }

    public function toggleStatus(Request $request)
    {
        $data = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        /** @var Driver $driver */
        $driver = auth('driver')->user();

        if ($data['is_online'] && ! $driver->hasFreshLocation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ative sua localização para ficar online.',
                'is_online' => $driver->is_online,
                'has_location' => false,
            ], 422);
        }

        $driver->update(['is_online' => $data['is_online']]);

        return response()->json(['success' => true, 'is_online' => $driver->is_online]);
    }

    public function acceptInvite(DriverCompany $driverCompany)
    {
        $this->authorizeInvite($driverCompany);

        $driverCompany->update([
            'status' => Driver::LINK_ACCEPTED,
            'responded_at' => now(),
        ]);

        return redirect()->route('drivers.dashboard')->with('success', 'Vínculo aceito!');
    }

    public function rejectInvite(DriverCompany $driverCompany)
    {
        $this->authorizeInvite($driverCompany);

        $driverCompany->update([
            'status' => Driver::LINK_REJECTED,
            'responded_at' => now(),
        ]);

        return redirect()->route('drivers.dashboard')->with('success', 'Convite recusado.');
    }

    private function authorizeInvite(DriverCompany $driverCompany): void
    {
        if ($driverCompany->driver_id !== auth('driver')->id()) {
            abort(403);
        }
    }

    private function driverPayload(Driver $driver): array
    {
        return [
            'name' => $driver->name,
            'is_online' => $driver->is_online,
            'has_location' => $driver->hasFreshLocation(),
        ];
    }

    private function activeDeliveriesPayload(Driver $driver): array
    {
        return $driver->activeDeliveries()
            ->with(['order', 'company'])
            ->get()
            ->map(fn (Delivery $delivery) => [
                'id' => $delivery->id,
                'tracking_token' => $delivery->tracking_token,
                'company_name' => $delivery->company->name,
                'order_short_id' => strtoupper(substr($delivery->order->uuid, 0, 8)),
                'driver_fee' => $delivery->driver_fee,
                'dispatched_at' => $delivery->dispatched_at?->toIso8601String(),
                'stage' => $delivery->isPickedUp() ? 'transit' : 'pickup',
            ])
            ->values()
            ->all();
    }

    private function todayStatsPayload(Driver $driver): array
    {
        $delivered = $driver->deliveries()
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereDate('delivered_at', now()->toDateString())
            ->get();

        return [
            'earnings' => (float) $delivered->sum('driver_fee'),
            'deliveries_count' => $delivered->count(),
        ];
    }
}
