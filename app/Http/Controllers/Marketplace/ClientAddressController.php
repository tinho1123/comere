<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ClientAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientAddressController extends Controller
{
    public function index()
    {
        $client = auth('client')->user();

        return response()->json([
            'success' => true,
            'data' => $client->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $client = auth('client')->user();

        $request->validate([
            'label' => 'required|string|max:50',
            'zip_code' => 'required|string|max:9',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            $client->addresses()->update(['is_default' => false]);
        }

        $isFirst = $client->addresses()->count() === 0;

        $address = $client->addresses()->create([
            'uuid' => Str::uuid(),
            'label' => $request->label,
            'zip_code' => $request->zip_code,
            'street' => $request->street,
            'number' => $request->number,
            'complement' => $request->complement,
            'neighborhood' => $request->neighborhood,
            'city' => $request->city,
            'state' => strtoupper($request->state),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'is_default' => $request->boolean('is_default') || $isFirst,
        ]);

        return response()->json(['success' => true, 'data' => $address], 201);
    }

    public function update(Request $request, ClientAddress $clientAddress)
    {
        $client = auth('client')->user();

        if ($clientAddress->client_id !== $client->id) {
            abort(403);
        }

        $request->validate([
            'label' => 'sometimes|string|max:50',
            'zip_code' => 'sometimes|string|max:9',
            'street' => 'sometimes|string|max:255',
            'number' => 'sometimes|string|max:20',
            'complement' => 'nullable|string|max:100',
            'neighborhood' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            $client->addresses()->update(['is_default' => false]);
        }

        $clientAddress->update($request->only([
            'label', 'zip_code', 'street', 'number', 'complement',
            'neighborhood', 'city', 'state', 'latitude', 'longitude', 'is_default',
        ]));

        return response()->json(['success' => true, 'data' => $clientAddress->fresh()]);
    }

    public function setDefault(ClientAddress $clientAddress)
    {
        $client = auth('client')->user();

        if ($clientAddress->client_id !== $client->id) {
            abort(403);
        }

        $client->addresses()->update(['is_default' => false]);
        $clientAddress->update(['is_default' => true]);

        return response()->json(['success' => true, 'data' => $clientAddress->fresh()]);
    }

    public function destroy(ClientAddress $clientAddress)
    {
        $client = auth('client')->user();

        if ($clientAddress->client_id !== $client->id) {
            abort(403);
        }

        $wasDefault = $clientAddress->is_default;
        $clientAddress->delete();

        if ($wasDefault) {
            $client->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['success' => true]);
    }
}
