<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
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
        ]);
    }

    public function poll()
    {
        /** @var Driver $driver */
        $driver = auth('driver')->user();

        return response()->json([
            'is_online' => $driver->is_online,
            'active_deliveries' => $this->activeDeliveriesPayload($driver),
            'pending_invites_count' => $driver->pendingInvites()->count(),
        ]);
    }

    public function toggleStatus(Request $request)
    {
        $data = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        /** @var Driver $driver */
        $driver = auth('driver')->user();
        $driver->update(['is_online' => $data['is_online']]);

        return response()->json(['is_online' => $driver->is_online]);
    }

    public function acceptInvite(DriverCompany $driverCompany)
    {
        $this->authorizeInvite($driverCompany);

        $driverCompany->update([
            'status' => Driver::LINK_ACCEPTED,
            'responded_at' => now(),
        ]);

        return redirect()->route('motoboy.dashboard')->with('success', 'Vínculo aceito!');
    }

    public function rejectInvite(DriverCompany $driverCompany)
    {
        $this->authorizeInvite($driverCompany);

        $driverCompany->update([
            'status' => Driver::LINK_REJECTED,
            'responded_at' => now(),
        ]);

        return redirect()->route('motoboy.dashboard')->with('success', 'Convite recusado.');
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
        ];
    }

    private function activeDeliveriesPayload(Driver $driver): array
    {
        return $driver->activeDeliveries()
            ->with(['order', 'company'])
            ->get()
            ->map(fn ($delivery) => [
                'id' => $delivery->id,
                'tracking_token' => $delivery->tracking_token,
                'company_name' => $delivery->company->name,
                'order_short_id' => strtoupper(substr($delivery->order->uuid, 0, 8)),
                'driver_fee' => $delivery->driver_fee,
                'dispatched_at' => $delivery->dispatched_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
