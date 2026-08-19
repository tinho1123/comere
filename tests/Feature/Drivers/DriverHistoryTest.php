<?php

namespace Tests\Feature\Drivers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCompany;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsAsMobileDevice;
use Tests\TestCase;

class DriverHistoryTest extends TestCase
{
    use InteractsAsMobileDevice;
    use RefreshDatabase;

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'Motoboy Teste',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function makeDelivery(Driver $driver, Company $company, Client $client, string $status, float $fee, ?\DateTimeInterface $deliveredAt = null, bool $isPaid = false): Delivery
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => $fee,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => $fee,
        ]);

        return Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'driver_fee' => $fee,
            'is_paid' => $isPaid,
            'dispatched_at' => now(),
            'delivered_at' => $deliveredAt,
        ]);
    }

    #[Test]
    public function it_shows_earnings_totals_for_the_authenticated_driver()
    {
        $driver = $this->makeDriver();
        $company = Company::factory()->create();
        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 10,
        ]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->makeDelivery($driver, $company, $client, Delivery::STATUS_DELIVERED, 12.50, now());
        $this->makeDelivery($driver, $company, $client, Delivery::STATUS_DELIVERED, 8.00, now()->subDay());
        $this->makeDelivery($driver, $company, $client, Delivery::STATUS_DISPATCHED, 15.00);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->get(route('drivers.history'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Driver/History')
            ->where('totals.deliveries_count', 2)
            ->where('totals.lifetime', 20.5)
            ->where('totals.today', 12.5)
        );
    }

    #[Test]
    public function a_driver_only_sees_their_own_completed_deliveries()
    {
        $driverA = $this->makeDriver();
        $driverB = Driver::create([
            'name' => 'Outro Motoboy',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->makeDelivery($driverA, $company, $client, Delivery::STATUS_DELIVERED, 10.00, now());
        $this->makeDelivery($driverB, $company, $client, Delivery::STATUS_DELIVERED, 99.00, now());

        $response = $this->withMobileUserAgent()->actingAs($driverA, 'driver')
            ->get(route('drivers.history'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totals.deliveries_count', 1)
            ->where('totals.lifetime', 10)
        );
    }

    #[Test]
    public function it_exposes_the_paid_status_per_delivery_and_a_to_receive_total()
    {
        $driver = $this->makeDriver();
        $company = Company::factory()->create();
        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 10,
        ]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->makeDelivery($driver, $company, $client, Delivery::STATUS_DELIVERED, 12.50, now(), isPaid: false);
        $this->makeDelivery($driver, $company, $client, Delivery::STATUS_DELIVERED, 8.00, now()->subDay(), isPaid: true);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->get(route('drivers.history'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totals.to_receive', 12.5)
            ->where('deliveries.0.is_paid', false)
            ->where('deliveries.1.is_paid', true)
        );
    }

    #[Test]
    public function guests_cannot_access_driver_history()
    {
        $response = $this->withMobileUserAgent()->get(route('drivers.history'));

        $response->assertRedirect();
    }
}
