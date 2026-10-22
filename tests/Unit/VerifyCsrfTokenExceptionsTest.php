<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerifyCsrfTokenExceptionsTest extends TestCase
{
    /**
     * Regressão: a view de rastreio de entrega (resources/views/delivery/tracking.blade.php)
     * chama fetch() direto pra todas essas rotas, sem enviar token CSRF. Em produção isso
     * derruba a chamada com 419 (que o front mostra como "Código incorreto" ou falha
     * silenciosa) — mas os testes de feature não pegam, porque o Laravel desliga a
     * verificação de CSRF inteira ao rodar PHPUnit. Esse teste trava a lista de exceção
     * em vez da rota real, pra garantir que nenhuma dessas 6 chamadas fique sem exceção
     * de novo (só "localizacao" e "pagamento" estavam na lista antes do fix).
     */
    #[Test]
    public function it_excepts_every_fetch_based_delivery_tracking_endpoint_from_csrf()
    {
        $except = app(VerifyCsrfToken::class)->getExcludedPaths();

        foreach ([
            'entrega/*/localizacao',
            'entrega/*/pagamento',
            'entrega/*/retirada',
            'entrega/*/concluir',
            'entrega/*/problema',
            'entrega/*/avaliacao',
        ] as $uri) {
            $this->assertContains($uri, $except, "A rota \"{$uri}\" precisa estar isenta de CSRF (a view chama fetch() sem token).");
        }
    }

    /**
     * Regressão: o service worker (public/sw.js) aciona Aprovar/Recusar de um
     * pedido direto do botão da notificação do sistema, usando URL assinada
     * em vez de sessão/CSRF (o service worker não tem como ler o cookie de
     * CSRF em todo navegador — Safari não implementa a Cookie Store API).
     * Sem essa exceção, a rota responde 419 antes mesmo do middleware
     * `signed` validar a assinatura.
     */
    #[Test]
    public function it_excepts_the_notification_approve_reject_endpoints_from_csrf()
    {
        $except = app(VerifyCsrfToken::class)->getExcludedPaths();

        foreach ([
            'admin/pedidos-pendentes/*/aprovar-notificacao',
            'admin/pedidos-pendentes/*/recusar-notificacao',
        ] as $uri) {
            $this->assertContains($uri, $except, "A rota \"{$uri}\" precisa estar isenta de CSRF (o service worker chama fetch() sem token, autenticado por URL assinada).");
        }
    }
}
