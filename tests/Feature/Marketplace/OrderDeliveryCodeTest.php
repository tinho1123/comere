<?php

namespace Tests\Feature\Marketplace;

use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDeliveryCodeTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Company $company, Client $client, string $status, string $paymentMethod = 'cash'): Order
    {
        return Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => $status,
            'channel' => Order::CHANNEL_ONLINE,
            'payment_method' => $paymentMethod,
            'subtotal' => 30,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 30,
        ]);
    }

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'Carlos Eduardo',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function a_shipped_order_exposes_the_pending_payment_confirmation_code()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_SHIPPED);

        $delivery = Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $this->makeDriver()->id,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_fee' => 8,
            'is_paid' => false,
            'dispatched_at' => now(),
            'picked_up_at' => now(),
        ]);

        $response = $this->actingAs($client, 'client')->get(route('marketplace.orders'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('orders.0.delivery.payment_confirmation_code', $delivery->payment_confirmation_code)
            ->where('orders.0.delivery.payment_collected', false)
        );
    }

    #[Test]
    public function a_delivered_order_exposes_who_collected_the_payment_and_when()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_DELIVERED);
        $driver = $this->makeDriver();

        $delivery = Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_DELIVERED,
            'driver_fee' => 8,
            'is_paid' => false,
            'dispatched_at' => now()->subMinutes(20),
            'picked_up_at' => now()->subMinutes(15),
            'delivered_at' => now(),
            'payment_collected' => true,
            'payment_collected_at' => now(),
        ]);

        $response = $this->actingAs($client, 'client')->get(route('marketplace.orders'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('orders.0.delivery.payment_confirmation_code', $delivery->payment_confirmation_code)
            ->where('orders.0.delivery.payment_collected', true)
            ->where('orders.0.delivery.driver_name', 'Carlos Eduardo')
            ->has('orders.0.delivery.payment_collected_at')
        );
    }

    #[Test]
    public function an_order_without_a_delivery_yet_has_a_null_delivery_payload()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $this->makeOrder($company, $client, Order::STATUS_PROCESSING);

        $response = $this->actingAs($client, 'client')->get(route('marketplace.orders'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('orders.0.delivery', null));
    }
}
