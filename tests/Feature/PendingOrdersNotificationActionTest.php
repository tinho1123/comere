<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regressão: o botão Aprovar/Recusar da notificação do sistema (push) é
 * disparado pelo service worker, que não tem sessão/CSRF disponível de forma
 * confiável em todo navegador — ver public/sw.js e
 * PendingOrdersController::approveFromNotification(). Essas rotas são
 * autorizadas por URL assinada (middleware `signed`) em vez de login, então
 * precisam funcionar sem usuário autenticado e rejeitar uma URL sem
 * assinatura válida (ou adulterada/expirada).
 */
class PendingOrdersNotificationActionTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingOrder(): Order
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 50,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 50,
        ]);
    }

    #[Test]
    public function it_approves_a_pending_order_via_signed_url_without_a_logged_in_user()
    {
        $order = $this->makePendingOrder();

        $url = URL::temporarySignedRoute('admin.pending-orders.approve-signed', now()->addHours(2), ['order' => $order->uuid]);

        $response = $this->postJson($url);

        $response->assertOk()->assertJson(['success' => true, 'status' => Order::STATUS_PROCESSING]);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
    }

    #[Test]
    public function it_rejects_a_pending_order_via_signed_url_without_a_logged_in_user()
    {
        $order = $this->makePendingOrder();

        $url = URL::temporarySignedRoute('admin.pending-orders.reject-signed', now()->addHours(2), ['order' => $order->uuid]);

        $response = $this->postJson($url);

        $response->assertOk()->assertJson(['success' => true, 'status' => Order::STATUS_CANCELLED]);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    #[Test]
    public function it_refuses_the_approve_endpoint_without_a_valid_signature()
    {
        $order = $this->makePendingOrder();

        $response = $this->postJson("/admin/pedidos-pendentes/{$order->uuid}/aprovar-notificacao");

        $response->assertForbidden();
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    #[Test]
    public function it_refuses_the_reject_endpoint_with_a_tampered_signature()
    {
        $order = $this->makePendingOrder();

        $url = URL::temporarySignedRoute('admin.pending-orders.reject-signed', now()->addHours(2), ['order' => $order->uuid]);
        $tampered = $url.'0';

        $response = $this->postJson($tampered);

        $response->assertForbidden();
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
