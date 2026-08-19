<?php

namespace Tests\Unit\Order;

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

class OrderDeliveryResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Company $company, Client $client, string $status): Order
    {
        return Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => $status,
            'channel' => Order::CHANNEL_ONLINE,
            'payment_method' => 'cash',
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
    public function it_flags_a_shipped_order_with_a_failed_delivery_as_unresolved()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_SHIPPED);

        Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $this->makeDriver()->id,
            'status' => Delivery::STATUS_FAILED,
            'failure_reason' => 'customer_unavailable',
            'driver_fee' => 8,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);

        $this->assertTrue($order->fresh()->hasUnresolvedFailedDelivery());
    }

    #[Test]
    public function it_does_not_flag_a_shipped_order_with_an_active_delivery()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_SHIPPED);

        Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $this->makeDriver()->id,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_fee' => 8,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);

        $this->assertFalse($order->fresh()->hasUnresolvedFailedDelivery());
    }

    #[Test]
    public function it_does_not_flag_an_order_without_a_delivery()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_PROCESSING);

        $this->assertFalse($order->hasUnresolvedFailedDelivery());
    }

    #[Test]
    public function it_translates_the_failure_reason_to_a_human_label()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = $this->makeOrder($company, $client, Order::STATUS_SHIPPED);

        $delivery = Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $this->makeDriver()->id,
            'status' => Delivery::STATUS_FAILED,
            'failure_reason' => 'wrong_address',
            'driver_fee' => 8,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);

        $this->assertEquals('Endereço não encontrado', $delivery->failureReasonLabel());
    }
}
