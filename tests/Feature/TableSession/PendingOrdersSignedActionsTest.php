<?php

namespace Tests\Feature\TableSession;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The push notification's "Aprovar"/"Recusar" action buttons POST from a
 * service worker, which can't reliably read the CSRF cookie across every
 * browser (especially mobile/background) to call the session-authenticated
 * routes. These signed routes don't need a session or CSRF token at all —
 * only a valid signature generated server-side for that specific order.
 */
class PendingOrdersSignedActionsTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(Company $company): Order
    {
        $client = Client::factory()->create(['company_id' => $company->id]);

        return Order::create([
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
    }

    #[Test]
    public function a_validly_signed_url_approves_the_order_without_any_session()
    {
        $company = Company::factory()->create();
        $order = $this->pendingOrder($company);

        $url = URL::temporarySignedRoute('admin.pending-orders.approve.signed', now()->addDays(3), ['order' => $order->uuid]);

        $response = $this->postJson($url);

        $response->assertOk();
        $response->assertJson(['success' => true, 'status' => Order::STATUS_PROCESSING]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PROCESSING]);
    }

    #[Test]
    public function a_validly_signed_url_rejects_the_order_without_any_session()
    {
        $company = Company::factory()->create();
        $order = $this->pendingOrder($company);

        $url = URL::temporarySignedRoute('admin.pending-orders.reject.signed', now()->addDays(3), ['order' => $order->uuid]);

        $response = $this->postJson($url);

        $response->assertOk();
        $response->assertJson(['success' => true, 'status' => Order::STATUS_CANCELLED]);
    }

    #[Test]
    public function rejecting_an_order_that_is_no_longer_pending_returns_a_conflict()
    {
        $company = Company::factory()->create();
        $order = $this->pendingOrder($company);
        $order->approve();

        $url = URL::temporarySignedRoute('admin.pending-orders.reject.signed', now()->addDays(3), ['order' => $order->uuid]);

        $response = $this->postJson($url);

        $response->assertStatus(409);
    }

    #[Test]
    public function an_unsigned_request_to_the_approve_route_is_rejected()
    {
        $company = Company::factory()->create();
        $order = $this->pendingOrder($company);

        $response = $this->postJson("/admin/pedidos-pendentes/{$order->uuid}/aprovar-assinado");

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PENDING]);
    }

    #[Test]
    public function a_signature_for_a_different_order_does_not_approve_this_one()
    {
        $company = Company::factory()->create();
        $order = $this->pendingOrder($company);
        $otherOrder = $this->pendingOrder($company);

        $urlForOther = URL::temporarySignedRoute('admin.pending-orders.approve.signed', now()->addDays(3), ['order' => $otherOrder->uuid]);
        $tamperedUrl = str_replace($otherOrder->uuid, $order->uuid, $urlForOther);

        $response = $this->postJson($tamperedUrl);

        $response->assertStatus(403);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PENDING]);
    }

    #[Test]
    public function pending_marketplace_orders_endpoint_includes_valid_signed_action_urls()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $order = $this->pendingOrder($company);

        $response = $this->actingAs($user)
            ->getJson('/admin/pedidos-pendentes/marketplace?company='.$company->uuid);

        $response->assertOk();
        $payload = $response->json('orders.0');

        $this->assertNotEmpty($payload['approve_url']);
        $this->assertNotEmpty($payload['reject_url']);

        // As URLs retornadas devem de fato aprovar/recusar quando acessadas.
        $approveResponse = $this->postJson($payload['approve_url']);
        $approveResponse->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_PROCESSING]);
    }
}
