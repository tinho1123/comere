<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\TableSessionItem;
use App\Services\DistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function approveOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

        $order->approve();

        return response()->json(['success' => true, 'status' => $order->status]);
    }

    public function rejectOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

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
        ];
    }
}
