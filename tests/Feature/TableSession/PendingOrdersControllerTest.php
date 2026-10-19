<?php

namespace Tests\Feature\TableSession;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PendingOrdersControllerTest extends TestCase
{
    use RefreshDatabase;

    private function openSessionWithItems(Company $company, int $pending, int $delivered = 0): TableSession
    {
        $category = ProductsCategories::forceCreate(['name' => 'Categoria de teste']);

        $product = Product::forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Produto de teste',
            'description' => 'Produto de teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 100,
            'active' => true,
        ]);

        $table = TableModel::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'name' => 'Mesa 1',
            'is_active' => true,
        ]);

        $session = TableSession::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'table_id' => $table->id,
            'status' => 'open',
            'opened_at' => now(),
            'total_amount' => 0,
        ]);

        for ($i = 0; $i < $pending; $i++) {
            TableSessionItem::create([
                'table_session_id' => $session->id,
                'product_id' => $product->id,
                'product_name' => 'Item pendente '.$i,
                'quantity' => 1,
                'unit_price' => 10,
                'total_amount' => 10,
                'is_delivered' => false,
            ]);
        }

        for ($i = 0; $i < $delivered; $i++) {
            TableSessionItem::create([
                'table_session_id' => $session->id,
                'product_id' => $product->id,
                'product_name' => 'Item entregue '.$i,
                'quantity' => 1,
                'unit_price' => 10,
                'total_amount' => 10,
                'is_delivered' => true,
                'delivered_at' => now(),
            ]);
        }

        return $session;
    }

    #[Test]
    public function it_counts_only_pending_items_for_the_authenticated_users_company()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $session = $this->openSessionWithItems($company, pending: 2, delivered: 3);

        $response = $this->actingAs($user)
            ->getJson('/admin/pedidos-pendentes?company='.$company->uuid);

        $response->assertStatus(200);
        $response->assertJson([
            'count' => 2,
            'latest_item_id' => $session->items()->max('id'),
        ]);
    }

    #[Test]
    public function it_does_not_count_pending_items_from_a_different_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $this->openSessionWithItems($otherCompany, pending: 5);

        $response = $this->actingAs($user)
            ->getJson('/admin/pedidos-pendentes?company='.$company->uuid);

        $response->assertStatus(200);
        $response->assertJson([
            'count' => 0,
            'latest_item_id' => null,
        ]);
    }

    #[Test]
    public function it_forbids_a_user_without_access_to_the_company()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/admin/pedidos-pendentes?company='.$company->uuid);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_reports_the_latest_order_id_and_pending_orders_count_for_the_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $client = Client::factory()->create(['company_id' => $company->id]);
        $otherClient = Client::factory()->create(['company_id' => $otherCompany->id]);

        Order::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        $lastOrder = Order::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        Order::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/admin/pedidos-pendentes?company='.$company->uuid);

        $response->assertStatus(200);
        $response->assertJson([
            'pending_orders_count' => 1,
            'latest_order_id' => $lastOrder->id,
        ]);
    }
}
