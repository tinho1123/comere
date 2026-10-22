<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Admin\Resources\TableSessionResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use App\Services\DistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PendingOrdersController extends Controller
{
    public function count(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $openSessionItems = TableSessionItem::whereHas('session', function ($query) use ($company): void {
            $query->where('company_id', $company->id)->where('status', 'open');
        });

        $count = (clone $openSessionItems)->where('is_delivered', false)->count();

        // Used by the help-guide tour (resources/views/filament/help-guide.blade.php)
        // to jump straight to a table session that actually has a pending item,
        // instead of guessing which row in the Mesas list to point at.
        $pendingItem = (clone $openSessionItems)->where('is_delivered', false)->with('session')->first();

        // latest_item_id tracks every item ever added (delivered or not), so the
        // frontend can detect a new order even if another item was delivered in
        // the same polling interval and the pending count itself didn't rise.
        $latestItemId = (clone $openSessionItems)->max('id');

        // latest_order_id tracks every marketplace order ever created for this
        // company, so the frontend can alert on a new order independently of
        // whatever status it's since moved to.
        $latestOrderId = Order::where('company_id', $company->id)->max('id');
        $pendingOrdersCount = Order::where('company_id', $company->id)
            ->where('status', Order::STATUS_PENDING)
            ->count();

        return response()->json([
            'count' => $count,
            'latest_item_id' => $latestItemId,
            'pending_orders_count' => $pendingOrdersCount,
            'latest_order_id' => $latestOrderId,
            'pending_session_uuid' => $pendingItem?->session?->uuid,
        ]);
    }

    /**
     * List pending marketplace orders for the current tenant, with enough
     * detail (items, total, address, distance, payment method) to render
     * the admin's "novo pedido" alert card without a second round-trip.
     */
    public function pendingMarketplaceOrders(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $orders = Order::where('company_id', $company->id)
            ->where('status', Order::STATUS_PENDING)
            ->where('channel', Order::CHANNEL_ONLINE)
            ->with(['client', 'items'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'orders' => $orders->map(fn (Order $order) => $this->formatOrder($order, $company))->all(),
        ]);
    }

    /**
     * List open table sessions that have at least one item still pending
     * delivery, with enough detail to render a "novo pedido na mesa" alert
     * card (same look as the marketplace card, no approve/reject — just a
     * link to the session).
     */
    public function pendingMesaOrders(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $sessions = TableSession::where('company_id', $company->id)
            ->where('status', 'open')
            ->whereHas('items', fn ($query) => $query->where('is_delivered', false))
            ->with([
                'table',
                'items' => fn ($query) => $query->where('is_delivered', false)->orderBy('id'),
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'sessions' => $sessions->map(fn (TableSession $session) => $this->formatMesaSession($session))->all(),
        ]);
    }

    public function approveOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

        return $this->doApprove($order);
    }

    public function rejectOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

        return $this->doReject($order);
    }

    /**
     * Same as approveOrder(), reached from a push notification's action
     * button. The `signed` middleware (route has no `auth` middleware) is
     * the only gate: the service worker can't reliably read the CSRF
     * cookie to build the header Laravel's session-based CSRF check needs
     * (cookieStore support is inconsistent across browsers, especially on
     * mobile/background), so this route authenticates via a per-order
     * signed URL generated server-side instead of a session.
     */
    public function approveOrderSigned(Request $request, Order $order): JsonResponse
    {
        return $this->doApprove($order);
    }

    public function rejectOrderSigned(Request $request, Order $order): JsonResponse
    {
        return $this->doReject($order);
    }

    private function doApprove(Order $order): JsonResponse
    {
        $order->approve();

        return response()->json(['success' => true, 'status' => $order->status]);
    }

    private function doReject(Order $order): JsonResponse
    {
        // O card pode estar com um estado defasado (até 8s de poll) — se o
        // pedido já saiu de "pending" (aprovado/despachado por outro admin),
        // recusar agora via Order::cancel() cancelaria uma etapa posterior
        // (ex.: devolveria estoque de um pedido já despachado). Em vez de
        // recusar um pedido que não é mais o que o card mostrava, avisa o
        // front para atualizar e descartar o card.
        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não está mais pendente.',
                'status' => $order->status,
            ], 409);
        }

        $order->cancel();

        return response()->json(['success' => true, 'status' => $order->status]);
    }

    private function formatOrder(Order $order, Company $company): array
    {
        $hasOrderAddress = (bool) ($order->delivery_street || $order->delivery_neighborhood);

        if ($hasOrderAddress) {
            // Endereço salvo no próprio pedido tem prioridade mesmo sem
            // coordenadas (a API de endereços do marketplace permite salvar
            // sem lat/lng) — o endereço padrão atual do cliente pode já ter
            // mudado desde que o pedido foi feito.
            $lat = $order->delivery_latitude ? (float) $order->delivery_latitude : null;
            $lng = $order->delivery_longitude ? (float) $order->delivery_longitude : null;
            $addressText = implode(', ', array_filter([
                trim(($order->delivery_street ?? '').', '.($order->delivery_number ?? '')),
                $order->delivery_neighborhood,
                ($order->delivery_city ?? '').($order->delivery_state ? '/'.$order->delivery_state : ''),
            ]));
            $neighborhood = $order->delivery_neighborhood;
        } else {
            $addr = $order->client?->defaultAddress()->first();
            $lat = $addr?->latitude ? (float) $addr->latitude : null;
            $lng = $addr?->longitude ? (float) $addr->longitude : null;
            $addressText = $addr
                ? "{$addr->street}, {$addr->number} — {$addr->neighborhood}, {$addr->city}/{$addr->state}"
                : null;
            $neighborhood = $addr?->neighborhood;
        }

        $storeLat = $company->latitude ? (float) $company->latitude : null;
        $storeLng = $company->longitude ? (float) $company->longitude : null;

        $distanceKm = ($lat && $lng && $storeLat && $storeLng)
            ? app(DistanceService::class)->calculate($storeLat, $storeLng, $lat, $lng)
            : null;

        // Mesmas URLs assinadas usadas na notificação push (ver
        // MarketplaceController::notifyCompanyOfNewOrder) — assim o botão
        // Aprovar/Recusar da notificação do sistema (aba em segundo plano)
        // usa a rota pública assinada em vez da rota autenticada, que exige
        // um header CSRF que o service worker não consegue montar de forma
        // confiável em todo navegador.
        $expiry = now()->addDays(3);

        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'created_at' => $order->created_at->toIso8601String(),
            'client_name' => $order->client?->name,
            'payment_method' => $order->payment_method,
            'payment_method_label' => Order::paymentOptions()[$order->payment_method] ?? $order->payment_method,
            'total_amount' => (float) $order->total_amount,
            'items_count' => $order->items->sum('quantity'),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->all(),
            'address' => $addressText,
            'neighborhood' => $neighborhood,
            'distance_km' => $distanceKm,
            'approve_url' => URL::temporarySignedRoute('admin.pending-orders.approve.signed', $expiry, ['order' => $order->uuid]),
            'reject_url' => URL::temporarySignedRoute('admin.pending-orders.reject.signed', $expiry, ['order' => $order->uuid]),
        ];
    }

    private function formatMesaSession(TableSession $session): array
    {
        // Usa o item pendente mais antigo como horário do card (igual ao
        // pedido do marketplace usar created_at do Order) — reflete há
        // quanto tempo o item espera, não quando a mesa foi aberta.
        $oldestPendingItem = $session->items->first();

        return [
            'id' => $session->id,
            'uuid' => $session->uuid,
            'created_at' => ($oldestPendingItem?->created_at ?? $session->opened_at)->toIso8601String(),
            'table_name' => $session->table?->name ?? 'Mesa',
            'guest_name' => $session->client_display_name !== '—' ? $session->client_display_name : null,
            'items' => $session->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->all(),
            'items_count' => $session->items->sum('quantity'),
            'url' => TableSessionResource::getUrl('view', ['record' => $session->uuid]),
        ];
    }
}
