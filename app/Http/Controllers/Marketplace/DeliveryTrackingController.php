<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryFeedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryTrackingController extends Controller
{
    public function show(string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)
            ->with(['order.items', 'order.client', 'driver', 'company'])
            ->firstOrFail();

        return view('delivery.tracking', [
            'delivery' => $delivery,
            'failureReasons' => Delivery::FAILURE_REASONS,
        ]);
    }

    public function updateLocation(Request $request, string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $delivery->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'location_updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function confirmPickup(Request $request, string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        if ($delivery->isPickedUp()) {
            return response()->json(['success' => false, 'message' => 'Retirada já foi confirmada.'], 409);
        }

        $data = $request->validate(['code' => 'required|string']);

        if ($data['code'] !== $delivery->pickup_code) {
            return response()->json(['success' => false, 'message' => 'Código de retirada incorreto.'], 422);
        }

        $delivery->update(['picked_up_at' => now()]);

        return response()->json(['success' => true, 'picked_up_at' => $delivery->picked_up_at->toIso8601String()]);
    }

    public function confirmPayment(Request $request, string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        if (! $delivery->isPickedUp()) {
            return response()->json(['success' => false, 'message' => 'Confirme a retirada na loja antes.'], 409);
        }

        $data = $request->validate(['code' => 'required|string']);

        if ($data['code'] !== $delivery->payment_confirmation_code) {
            return response()->json(['success' => false, 'message' => 'Código de confirmação incorreto.'], 422);
        }

        $delivery->update([
            'payment_collected' => true,
            'payment_collected_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function confirmDelivery(string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->with('order')->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        if (! $delivery->isPickedUp()) {
            return response()->json(['success' => false, 'message' => 'Confirme a retirada na loja antes.'], 409);
        }

        $requiresPayment = $delivery->order->payment_method && $delivery->order->payment_method !== 'credit';

        if ($requiresPayment && ! $delivery->payment_collected) {
            return response()->json(['success' => false, 'message' => 'Confirme o recebimento do pagamento antes.'], 409);
        }

        $delivery->update([
            'status' => Delivery::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $delivery->order->deliver();

        return response()->json(['success' => true]);
    }

    public function reportProblem(Request $request, string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Delivery::FAILURE_REASONS))],
            'notes' => 'nullable|string|max:500',
        ]);

        $delivery->update([
            'status' => Delivery::STATUS_FAILED,
            'failure_reason' => $data['reason'],
            'notes' => $data['notes'] ?? $delivery->notes,
        ]);

        return response()->json(['success' => true]);
    }

    public function submitFeedback(Request $request, string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DELIVERED) {
            return response()->json(['success' => false, 'message' => 'Só é possível avaliar após concluir a entrega.'], 409);
        }

        $data = $request->validate([
            'rating' => ['required', Rule::in([DeliveryFeedback::RATING_GOOD, DeliveryFeedback::RATING_BAD])],
        ]);

        DeliveryFeedback::updateOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'company_id' => $delivery->company_id,
                'driver_id' => $delivery->driver_id,
                'rating' => $data['rating'],
            ]
        );

        return response()->json(['success' => true]);
    }
}
