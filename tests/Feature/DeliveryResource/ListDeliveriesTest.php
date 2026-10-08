<?php

namespace Tests\Feature\DeliveryResource;

use App\Filament\Admin\Resources\DeliveryResource\Pages\ListDeliveries;
use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCompany;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->client = Client::factory()->create(['company_id' => $this->company->id]);

        $this->user->companies()->attach($this->company->id);
        $this->actingAs($this->user);

        Filament::setTenant($this->company);
    }

    private function makeDispatchedDelivery(bool $withoutTrackingToken = false): Delivery
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status' => Order::STATUS_SHIPPED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 50,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 50,
        ]);

        $driver = Driver::create([
            'name' => 'Motoboy Teste',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $this->company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 10,
            'responded_at' => now(),
        ]);

        $delivery = Delivery::create([
            'company_id' => $this->company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_fee' => 10,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);

        if ($withoutTrackingToken) {
            // Simulates a delivery created before the tracking_token column
            // was backfilled (see migration 2026_10_08_000001).
            $delivery->forceFill(['tracking_token' => null])->save();
        }

        return $delivery;
    }

    #[Test]
    public function it_can_render_deliveries_list_page()
    {
        $response = $this->get("/admin/{$this->company->uuid}/deliveries");

        $response->assertOk();
    }

    #[Test]
    public function it_loads_tabs_without_type_errors()
    {
        $this->makeDispatchedDelivery();

        Livewire::test(ListDeliveries::class)
            ->assertOk();
    }

    #[Test]
    public function it_renders_the_list_page_for_a_delivery_without_a_tracking_token()
    {
        $delivery = $this->makeDispatchedDelivery(withoutTrackingToken: true);

        $this->assertNull($delivery->tracking_token);

        Livewire::test(ListDeliveries::class)
            ->assertOk();
    }

    #[Test]
    public function tracking_url_self_heals_a_missing_token()
    {
        $delivery = $this->makeDispatchedDelivery(withoutTrackingToken: true);

        $url = $delivery->trackingUrl();

        $this->assertNotEmpty($delivery->tracking_token);
        $this->assertStringContainsString($delivery->tracking_token, $url);
        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'tracking_token' => $delivery->tracking_token,
        ]);
    }

    #[Test]
    public function the_backfill_migration_fills_missing_tracking_tokens()
    {
        $delivery = $this->makeDispatchedDelivery(withoutTrackingToken: true);

        (require base_path('database/migrations/2026_10_08_000001_backfill_delivery_tracking_tokens.php'))->up();

        $this->assertNotEmpty($delivery->refresh()->tracking_token);
    }
}
