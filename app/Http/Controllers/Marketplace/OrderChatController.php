<?php

namespace App\Http\Controllers\Marketplace;

use App\Filament\Admin\Resources\OrderResource;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderChatController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        $client = auth('client')->user();
        abort_unless($order->client_id === $client->id, 403);

        $order->messages()
            ->where('sender_type', OrderMessage::SENDER_COMPANY)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'chat_open' => $order->isChatOpen(),
            'messages' => $order->messages()->orderBy('created_at')->get()->map(fn (OrderMessage $m) => $this->format($m))->all(),
        ]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $client = auth('client')->user();
        abort_unless($order->client_id === $client->id, 403);
        abort_unless($order->isChatOpen(), 422);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $order->messages()->create([
            'company_id' => $order->company_id,
            'sender_type' => OrderMessage::SENDER_CLIENT,
            'sender_id' => $client->id,
            'author_name' => $client->name,
            'message' => $data['message'],
        ]);

        try {
            $ordersUrl = OrderResource::getUrl('index', ['tenant' => $order->company]);

            app(PushNotificationService::class)->notifyCompany(
                $order->company_id,
                'Nova mensagem sobre o pedido',
                "{$client->name}: {$data['message']}",
                "{$ordersUrl}?pedido={$order->uuid}"
            );
        } catch (\Throwable $e) {
            // A mensagem já foi salva; uma falha no push não pode virar um 500
            // para o cliente (que veria "erro ao enviar" numa mensagem que, na
            // verdade, já chegou e pode gerar duplicata num retry).
            Log::warning('Chat push notification failed', [
                'order_id' => $order->id,
                'company_id' => $order->company_id,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $this->format($message),
        ], 201);
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
