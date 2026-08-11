<?php

namespace Tests\Feature\Marketplace;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderQueuePositionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Company $company, Client $client, string $status, Carbon $createdAt): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => $status,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 10,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 10,
        ]);

        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    #[Test]
    public function it_counts_older_pending_and_processing_orders_ahead_in_the_queue()
    {
        $company = Company::factory()->create();
        $clientA = Client::factory()->create(['company_id' => $company->id]);
        $clientB = Client::factory()->create(['company_id' => $company->id]);
        $clientC = Client::factory()->create(['company_id' => $company->id]);

        $this->makeOrder($company, $clientA, Order::STATUS_PENDING, now()->subMinutes(30));
        $this->makeOrder($company, $clientB, Order::STATUS_PROCESSING, now()->subMinutes(20));
        $this->makeOrder($company, $clientC, Order::STATUS_PENDING, now()->subMinutes(10));

        $this->actingAs($clientA, 'client')
            ->get(route('marketplace.orders'))
            ->assertInertia(fn ($page) => $page->where('orders.0.queue_position', 0));

        $this->actingAs($clientC, 'client')
            ->get(route('marketplace.orders'))
            ->assertInertia(fn ($page) => $page->where('orders.0.queue_position', 2));
    }

    #[Test]
    public function it_does_not_expose_queue_position_once_shipped_or_delivered()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $this->makeOrder($company, $client, Order::STATUS_SHIPPED, now());

        $this->actingAs($client, 'client')
            ->get(route('marketplace.orders'))
            ->assertInertia(fn ($page) => $page->where('orders.0.queue_position', null));
    }

    #[Test]
    public function queue_position_ignores_orders_from_other_companies()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $otherClient = Client::factory()->create(['company_id' => $otherCompany->id]);

        $this->makeOrder($otherCompany, $otherClient, Order::STATUS_PENDING, now()->subMinutes(60));
        $this->makeOrder($company, $client, Order::STATUS_PENDING, now());

        $this->actingAs($client, 'client')
            ->get(route('marketplace.orders'))
            ->assertInertia(fn ($page) => $page->where('orders.0.queue_position', 0));
    }
}
