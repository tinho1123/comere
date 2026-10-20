<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderChatController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);

        $order->messages()
            ->where('sender_type', OrderMessage::SENDER_CLIENT)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'chat_open' => $order->isChatOpen(),
            'messages' => $order->messages()->orderBy('created_at')->get()->map(fn (OrderMessage $m) => $this->format($m))->all(),
        ]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessTenant($order->company), 403);
        abort_unless($order->isChatOpen(), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $order->messages()->create([
            'company_id' => $order->company_id,
            'sender_type' => OrderMessage::SENDER_COMPANY,
            'sender_id' => auth()->id(),
            'author_name' => $order->company->name,
            'message' => $data['message'],
        ]);

        return response()->json([
            'message' => $this->format($message),
        ], 201);
    }

    /**
     * Unread client-message count per order, for the admin pending-orders
     * poll to surface a chat badge alongside the existing order alerts.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $orderIds = OrderMessage::where('company_id', $company->id)
            ->where('sender_type', OrderMessage::SENDER_CLIENT)
            ->whereNull('read_at')
            ->whereHas('order', function ($query) {
                $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED]);
            })
            ->get()
            ->groupBy('order_id');

        return response()->json([
            'orders' => $orderIds->map(fn ($messages, $orderId) => [
                'order_id' => (int) $orderId,
                'order_uuid' => $messages->first()->order->uuid,
                'unread_count' => $messages->count(),
            ])->values()->all(),
        ]);
    }

    private function format(OrderMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'sender_type' => $message->sender_type,
            'author_name' => $message->author_name,
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
