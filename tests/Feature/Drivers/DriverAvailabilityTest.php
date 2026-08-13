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

class DriverAvailabilityTest extends TestCase
{
    use InteractsAsMobileDevice;
    use RefreshDatabase;

    private function makeDriver(bool $isOnline = false): Driver
    {
        return Driver::create([
            'name' => 'Motoboy Teste',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
            'is_online' => $isOnline,
        ]);
    }

    #[Test]
    public function a_driver_can_go_online_and_offline()
    {
        $driver = $this->makeDriver(isOnline: false);
        $driver->update(['last_latitude' => -23.55, 'last_longitude' => -46.63, 'last_location_at' => now()]);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.status.toggle'), ['is_online' => true]);

        $response->assertOk();
        $response->assertJson(['is_online' => true]);
        $this->assertTrue($driver->fresh()->is_online);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.status.toggle'), ['is_online' => false]);

        $response->assertOk();
        $response->assertJson(['is_online' => false]);
        $this->assertFalse($driver->fresh()->is_online);
    }

    #[Test]
    public function a_driver_cannot_go_online_without_a_fresh_gps_location()
    {
        $driver = $this->makeDriver(isOnline: false);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.status.toggle'), ['is_online' => true]);

        $response->assertStatus(422);
        $this->assertFalse($driver->fresh()->is_online);
    }

    #[Test]
    public function a_driver_cannot_go_online_with_a_stale_gps_location()
    {
        $driver = $this->makeDriver(isOnline: false);
        $driver->update(['last_latitude' => -23.55, 'last_longitude' => -46.63, 'last_location_at' => now()->subMinutes(10)]);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.status.toggle'), ['is_online' => true]);

        $response->assertStatus(422);
        $this->assertFalse($driver->fresh()->is_online);
    }

    #[Test]
    public function a_driver_can_go_offline_even_without_gps()
    {
        $driver = $this->makeDriver(isOnline: true);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.status.toggle'), ['is_online' => false]);

        $response->assertOk();
        $this->assertFalse($driver->fresh()->is_online);
    }

    #[Test]
    public function it_updates_the_drivers_last_known_location()
    {
        $driver = $this->makeDriver(isOnline: false);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('drivers.location.update'), ['latitude' => -23.55, 'longitude' => -46.63]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'has_location' => true]);
        $driver->refresh();
        $this->assertEquals(-23.55, $driver->last_latitude);
        $this->assertEquals(-46.63, $driver->last_longitude);
        $this->assertTrue($driver->hasFreshLocation());
    }

    #[Test]
    public function guests_cannot_toggle_availability()
    {
        $response = $this->postJson(route('drivers.status.toggle'), ['is_online' => true]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function poll_returns_the_drivers_active_deliveries_and_status()
    {
        $driver = $this->makeDriver(isOnline: true);
        $company = Company::factory()->create();
        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 10,
        ]);

        $client = Client::factory()->create(['company_id' => $company->id]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_SHIPPED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 20,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 20,
        ]);

        $delivery = Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_fee' => 10,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')->getJson(route('drivers.poll'));

        $response->assertOk();
        $response->assertJson([
            'is_online' => true,
            'pending_invites_count' => 0,
        ]);
        $response->assertJsonPath('active_deliveries.0.id', $delivery->id);
        $response->assertJsonPath('active_deliveries.0.tracking_token', $delivery->tracking_token);
    }
}
