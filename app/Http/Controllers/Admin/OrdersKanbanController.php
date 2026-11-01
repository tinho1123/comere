<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\TableSessionResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrdersKanbanController extends Controller
{
    /**
     * Columns payload for the full-screen "Painel de Pedidos" kanban
     * (marketplace orders + table/mesa sessions in the same stages).
     * Polled by resources/views/filament/admin/orders-kanban.blade.php.
     */
    public function data(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $orders = Order::where('company_id', $company->id)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->with(['client', 'items', 'delivery.driver', 'messages' => fn ($q) => $q
                ->where('sender_type', OrderMessage::SENDER_CLIENT)
                ->whereNull('read_at'),
            ])
            ->orderByDesc('id')
            ->get();

        $sessions = TableSession::where('company_id', $company->id)
            ->with(['table', 'client', 'items'])
            ->orderByDesc('id')
            ->get();

        $columns = [
            'pendente' => [],
            'em_preparo' => [],
            'pronto' => [],
            'entregue' => [],
        ];

        foreach ($orders as $order) {
            $columns[$this->orderColumn($order)][] = $this->formatOrder($order, $company);
        }

        foreach ($sessions as $session) {
            $columns[$this->sessionColumn($session)][] = $this->formatSession($session, $company);
        }

        return response()->json([
            'columns' => $columns,
            'stage_times' => $this->stageTimes($company),
        ]);
    }

    /**
     * Drag-and-drop between columns, for the two marketplace transitions
     * that need no extra input: Pendente→Em preparo (approve) and
     * Pronto→Entregue (mark delivered). Em preparo→Pronto needs a driver
     * picked from a form (see OrderResource's "ship" action), so it isn't
     * offered as a drag target — only the normal detail modal does that.
     * Rejecting is likewise never done by dragging, only from the modal.
     */
    public function advance(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

        $data = $request->validate([
            'to' => ['required', 'in:em_preparo,entregue'],
        ]);

        if ($data['to'] === 'em_preparo') {
            if (! $order->canBeApproved()) {
                return response()->json(['success' => false, 'message' => 'Pedido não está mais pendente.'], 409);
            }

            $order->approve();
        } else {
            if (! $order->canBeDelivered() || $order->hasUnresolvedFailedDelivery()) {
                return response()->json(['success' => false, 'message' => 'Pedido não pode ser marcado como entregue agora.'], 409);
            }

            $order->deliver();
        }

        return response()->json(['success' => true, 'status' => $order->status]);
    }

    private function orderColumn(Order $order): string
    {
        return match ($order->status) {
            Order::STATUS_PENDING => 'pendente',
            Order::STATUS_PROCESSING => 'em_preparo',
            Order::STATUS_SHIPPED => 'pronto',
            Order::STATUS_DELIVERED => 'entregue',
            default => 'entregue',
        };
    }

    private function sessionColumn(TableSession $session): string
    {
        if ($session->status === 'closed') {
            return 'entregue';
        }

        $total = $session->items->count();
        $delivered = $session->items->where('is_delivered', true)->count();

        if ($total === 0 || $delivered === 0) {
            return 'pendente';
        }

        if ($delivered < $total) {
            return 'em_preparo';
        }

        return 'pronto';
    }

    private function formatOrder(Order $order, Company $company): array
    {
        return [
            'type' => 'order',
            'id' => $order->id,
            'uuid' => $order->uuid,
            'label' => '#'.$order->id,
            'channel' => $order->channel,
            'customer' => $order->client?->name ?? 'Balcão',
            'items_count' => $order->items->sum('quantity'),
            'total' => (float) $order->total_amount,
            'payment_method' => Order::paymentOptions()[$order->payment_method] ?? $order->payment_method,
            'needs_approval' => $order->status === Order::STATUS_PENDING,
            'unread_messages' => $order->messages->count(),
            'created_at' => $order->created_at->toIso8601String(),
            'driver_name' => $order->delivery?->driver?->name,
            'delivery_failed' => $order->delivery?->status === Delivery::STATUS_FAILED,
            'url' => OrderResource::getUrl('index', ['tenant' => $company]).'?pedido='.$order->uuid,
        ];
    }

    private function formatSession(TableSession $session, Company $company): array
    {
        $total = $session->items->count();
        $delivered = $session->items->where('is_delivered', true)->count();

        return [
            'type' => 'session',
            'id' => $session->id,
            'uuid' => $session->uuid,
            'label' => $session->table?->name ?? 'Mesa',
            'customer' => $session->guest_name ?? $session->client?->name ?? 'Visitante',
            'items_total' => $total,
            'items_delivered' => $delivered,
            'total' => (float) $session->total_amount,
            'needs_approval' => false,
            'unread_messages' => 0,
            'created_at' => ($session->opened_at ?? $session->created_at)->toIso8601String(),
            'closed' => $session->status === 'closed',
            'url' => TableSessionResource::getUrl('view', ['record' => $session->uuid, 'tenant' => $company]),
        ];
    }

    /**
     * Average minutes spent in each stage today, from marketplace orders
     * that have already moved past that stage (pedido-level timestamps are
     * reliable; mesa sessions only track a single delivered_at per item,
     * so they're left out of this stat).
     */
    private function stageTimes(Company $company): array
    {
        $today = Order::where('company_id', $company->id)
            ->whereDate('created_at', Carbon::today())
            ->get();

        return [
            'pendente' => $this->averageMinutes($today->filter(fn ($o) => $o->confirmed_at), 'created_at', 'confirmed_at'),
            'em_preparo' => $this->averageMinutes($today->filter(fn ($o) => $o->shipped_at), 'confirmed_at', 'shipped_at'),
            'pronto' => $this->averageMinutes($today->filter(fn ($o) => $o->delivered_at), 'shipped_at', 'delivered_at'),
        ];
    }

    private function averageMinutes($orders, string $from, string $to): ?float
    {
        $diffs = $orders
            ->filter(fn ($order) => $order->{$from} && $order->{$to})
            ->map(fn ($order) => $order->{$from}->diffInSeconds($order->{$to}));

        if ($diffs->isEmpty()) {
            return null;
        }

        return round($diffs->avg() / 60, 1);
    }
}
