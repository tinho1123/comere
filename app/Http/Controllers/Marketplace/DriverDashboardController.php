<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverCompany;

class DriverDashboardController extends Controller
{
    public function show()
    {
        /** @var Driver $driver */
        $driver = auth('driver')->user();

        $pendingInvites = $driver->pendingInvites()->get();
        $acceptedCompanies = $driver->acceptedCompanies()->get();
        $activeDeliveries = $driver->activeDeliveries()->with(['order', 'company'])->get();

        return view('motoboy.dashboard', [
            'driver' => $driver,
            'pendingInvites' => $pendingInvites,
            'acceptedCompanies' => $acceptedCompanies,
            'activeDeliveries' => $activeDeliveries,
        ]);
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
}
