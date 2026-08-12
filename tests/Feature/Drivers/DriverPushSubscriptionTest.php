<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use App\Models\DriverPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsAsMobileDevice;
use Tests\TestCase;

class DriverPushSubscriptionTest extends TestCase
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

    #[Test]
    public function a_driver_can_subscribe_to_push_notifications()
    {
        $driver = $this->makeDriver();

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('motoboy.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'public_key' => 'public-key',
                'auth_token' => 'auth-token',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('driver_push_subscriptions', [
            'driver_id' => $driver->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    #[Test]
    public function subscribing_twice_with_the_same_endpoint_updates_instead_of_duplicating()
    {
        $driver = $this->makeDriver();

        $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('motoboy.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'public_key' => 'old-key',
            ])->assertOk();

        $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('motoboy.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'public_key' => 'new-key',
            ])->assertOk();

        $this->assertEquals(1, DriverPushSubscription::where('driver_id', $driver->id)->count());
        $this->assertDatabaseHas('driver_push_subscriptions', [
            'driver_id' => $driver->id,
            'public_key' => 'new-key',
        ]);
    }

    #[Test]
    public function a_driver_can_unsubscribe()
    {
        $driver = $this->makeDriver();
        DriverPushSubscription::create([
            'driver_id' => $driver->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('motoboy.push.unsubscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('driver_push_subscriptions', [
            'driver_id' => $driver->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    #[Test]
    public function a_driver_cannot_unsubscribe_another_drivers_subscription()
    {
        $driver = $this->makeDriver();
        $otherDriver = Driver::create([
            'name' => 'Outro Motoboy',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        DriverPushSubscription::create([
            'driver_id' => $otherDriver->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);

        $this->withMobileUserAgent()->actingAs($driver, 'driver')
            ->postJson(route('motoboy.push.unsubscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            ])->assertOk();

        $this->assertDatabaseHas('driver_push_subscriptions', [
            'driver_id' => $otherDriver->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    #[Test]
    public function guests_cannot_subscribe()
    {
        $response = $this->withMobileUserAgent()
            ->postJson(route('motoboy.push.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            ]);

        $response->assertUnauthorized();
    }
}
