<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;

class DeliveryTrackingController extends Controller
{
    public function show(string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)
            ->with(['order', 'driver', 'company'])
            ->firstOrFail();

        return view('delivery.tracking', ['delivery' => $delivery]);
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

    public function confirmPayment(string $token)
    {
        $delivery = Delivery::where('tracking_token', $token)->firstOrFail();

        if ($delivery->status !== Delivery::STATUS_DISPATCHED) {
            return response()->json(['success' => false, 'message' => 'Entrega não está mais em andamento.'], 409);
        }

        $delivery->update([
            'payment_collected' => true,
            'payment_collected_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
