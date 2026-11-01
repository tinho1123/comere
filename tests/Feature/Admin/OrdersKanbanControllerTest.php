<?php

namespace Tests\Feature\Admin;

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

class OrdersKanbanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Company $company, string $status, array $overrides = []): Order
    {
        $client = Client::factory()->create(['company_id' => $company->id]);

        return Order::create(array_merge([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => $status,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 10,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 10,
        ], $overrides));
    }

    private function openSessionWithItems(Company $company, int $pending, int $delivered = 0): TableSession
    {
        $category = ProductsCategories::firstOrCreate(['name' => 'Categoria de teste']);

        $product = Product::forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Produto de teste '.$company->id.'-'.uniqid(),
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
            'name' => 'Mesa '.uniqid(),
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
    public function it_places_orders_and_sessions_in_the_right_column_for_the_authenticated_users_company()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $pending = $this->makeOrder($company, Order::STATUS_PENDING);
        $processing = $this->makeOrder($company, Order::STATUS_PROCESSING);
        $shipped = $this->makeOrder($company, Order::STATUS_SHIPPED);
        $delivered = $this->makeOrder($company, Order::STATUS_DELIVERED);
        $this->makeOrder($company, Order::STATUS_CANCELLED);

        $freshSession = $this->openSessionWithItems($company, pending: 2, delivered: 0);
        $partialSession = $this->openSessionWithItems($company, pending: 1, delivered: 1);
        $readySession = $this->openSessionWithItems($company, pending: 0, delivered: 2);

        $response = $this->actingAs($user)
            ->getJson('/admin/painel-pedidos/dados?company='.$company->uuid);

        $response->assertStatus(200);
        $columns = $response->json('columns');

        $this->assertSame([$pending->id], array_column(
            array_filter($columns['pendente'], fn ($c) => $c['type'] === 'order'), 'id'
        ));
        $this->assertSame([$freshSession->id], array_column(
            array_filter($columns['pendente'], fn ($c) => $c['type'] === 'session'), 'id'
        ));

        $this->assertSame([$processing->id], array_column(
            array_filter($columns['em_preparo'], fn ($c) => $c['type'] === 'order'), 'id'
        ));
        $this->assertSame([$partialSession->id], array_column(
            array_filter($columns['em_preparo'], fn ($c) => $c['type'] === 'session'), 'id'
        ));

        $this->assertSame([$shipped->id], array_column(
            array_filter($columns['pronto'], fn ($c) => $c['type'] === 'order'), 'id'
        ));
        $this->assertSame([$readySession->id], array_column(
            array_filter($columns['pronto'], fn ($c) => $c['type'] === 'session'), 'id'
        ));

        $this->assertSame([$delivered->id], array_column(
            array_filter($columns['entregue'], fn ($c) => $c['type'] === 'order'), 'id'
        ));

        $allIds = array_merge(...array_values(array_map(fn ($cards) => array_column(
            array_filter($cards, fn ($c) => $c['type'] === 'order'), 'id'
        ), $columns)));
        $this->assertNotContains(Order::STATUS_CANCELLED, $allIds, 'cancelled orders must stay hidden');
    }

    #[Test]
    public function it_marks_pending_marketplace_orders_as_needing_approval()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $this->makeOrder($company, Order::STATUS_PENDING);

        $response = $this->actingAs($user)
            ->getJson('/admin/painel-pedidos/dados?company='.$company->uuid);

        $response->assertStatus(200);
        $this->assertTrue($response->json('columns.pendente.0.needs_approval'));
    }

    #[Test]
    public function it_does_not_include_data_from_a_different_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $this->makeOrder($otherCompany, Order::STATUS_PENDING);
        $this->openSessionWithItems($otherCompany, pending: 1);

        $response = $this->actingAs($user)
            ->getJson('/admin/painel-pedidos/dados?company='.$company->uuid);

        $response->assertStatus(200);
        foreach ($response->json('columns') as $cards) {
            $this->assertSame([], $cards);
        }
    }

    #[Test]
    public function it_forbids_a_user_without_access_to_the_company()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/admin/painel-pedidos/dados?company='.$company->uuid);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_approves_a_pending_order_when_dragged_to_em_preparo()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $order = $this->makeOrder($company, Order::STATUS_PENDING);

        $response = $this->actingAs($user)
            ->postJson("/admin/painel-pedidos/pedidos/{$order->uuid}/avancar", ['to' => 'em_preparo']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => Order::STATUS_PROCESSING]);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    #[Test]
    public function it_delivers_a_shipped_order_when_dragged_to_entregue()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $order = $this->makeOrder($company, Order::STATUS_SHIPPED);

        $response = $this->actingAs($user)
            ->postJson("/admin/painel-pedidos/pedidos/{$order->uuid}/avancar", ['to' => 'entregue']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'status' => Order::STATUS_DELIVERED]);
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    #[Test]
    public function it_refuses_to_advance_an_order_that_is_not_in_the_expected_status()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $order = $this->makeOrder($company, Order::STATUS_PROCESSING);

        $response = $this->actingAs($user)
            ->postJson("/admin/painel-pedidos/pedidos/{$order->uuid}/avancar", ['to' => 'em_preparo']);

        $response->assertStatus(409);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    #[Test]
    public function it_rejects_an_unsupported_transition()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $order = $this->makeOrder($company, Order::STATUS_PROCESSING);

        $response = $this->actingAs($user)
            ->postJson("/admin/painel-pedidos/pedidos/{$order->uuid}/avancar", ['to' => 'pronto']);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_forbids_advancing_an_order_from_a_different_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $order = $this->makeOrder($otherCompany, Order::STATUS_PENDING);

        $response = $this->actingAs($user)
            ->postJson("/admin/painel-pedidos/pedidos/{$order->uuid}/avancar", ['to' => 'em_preparo']);

        $response->assertStatus(403);
    }
}
