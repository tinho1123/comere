<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TableController extends Controller
{
    private function cookieName(string $uuid): string
    {
        return 'table_device_'.$uuid;
    }

    private function deviceToken(Request $request, string $uuid): ?string
    {
        return $request->cookie($this->cookieName($uuid));
    }

    public function show(Request $request, string $uuid)
    {
        $table = TableModel::where('uuid', $uuid)
            ->with(['activeSession.items.product', 'activeSession.client', 'company'])
            ->firstOrFail();

        $session = $table->activeSession;
        $deviceToken = $this->deviceToken($request, $uuid);
        $cookie = null;

        if ($session && $session->isOpen()) {
            if (! $session->device_token) {
                // Sessão aberta pelo admin — primeiro cliente que chegar reivindica o dispositivo
                $newToken = (string) Str::uuid();
                $session->update(['device_token' => $newToken]);
                $cookie = cookie($this->cookieName($uuid), $newToken, 60 * 24);
            } elseif ($deviceToken !== $session->device_token) {
                // Dispositivo diferente — mesa ocupada
                return view('table.occupied', ['table' => $table]);
            }
        }

        $products = Product::where('company_id', $table->company_id)
            ->where('active', true)
            ->where('quantity', '>', 0)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => $p->category?->name ?? 'Outros');

        $view = view('table.show', ['table' => $table, 'products' => $products]);

        if ($cookie) {
            return response($view)->withCookie($cookie);
        }

        return $view;
    }

    public function registerName(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'guest_name' => ['required', 'string', 'max:100'],
        ]);

        $table = TableModel::where('uuid', $uuid)->firstOrFail();
        $session = $table->activeSession;

        if (! $this->isDeviceOwner($request, $uuid, $session)) {
            return redirect()->route('table.show', $uuid);
        }

        if ($session && $session->isOpen() && ! $session->client_id && ! $session->guest_name) {
            $session->update(['guest_name' => $request->guest_name]);
        }

        return redirect()->route('table.show', $uuid)->with('success', 'Nome registrado com sucesso!');
    }

    public function open(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'guest_name' => ['required', 'string', 'max:100'],
        ]);

        $table = TableModel::where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        if ($table->activeSession) {
            return redirect()->route('table.show', $uuid);
        }

        $token = (string) Str::uuid();

        TableSession::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $table->company_id,
            'table_id' => $table->id,
            'guest_name' => $request->guest_name,
            'device_token' => $token,
            'status' => 'open',
            'opened_at' => now(),
            'total_amount' => 0,
        ]);

        return redirect()->route('table.show', $uuid)
            ->withCookie(cookie($this->cookieName($uuid), $token, 60 * 24))
            ->with('success', 'Mesa aberta! Bem-vindo, '.$request->guest_name.'.');
    }

    public function addItem(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $table = TableModel::where('uuid', $uuid)->firstOrFail();
        $session = $table->activeSession;

        if (! $session || ! $session->isOpen()) {
            return redirect()->route('table.show', $uuid)->with('error', 'Não há uma sessão aberta nesta mesa.');
        }

        if (! $this->isDeviceOwner($request, $uuid, $session)) {
            return redirect()->route('table.show', $uuid);
        }

        $product = Product::where('id', $request->product_id)
            ->where('company_id', $table->company_id)
            ->where('active', true)
            ->firstOrFail();

        $qty = (int) $request->quantity;

        TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * $qty,
        ]);

        return redirect()->route('table.show', $uuid)->with('success', $qty.'x '.$product->name.' adicionado ao pedido!');
    }

    private function isDeviceOwner(Request $request, string $uuid, ?TableSession $session): bool
    {
        if (! $session || ! $session->device_token) {
            return true;
        }

        return $this->deviceToken($request, $uuid) === $session->device_token;
    }
}
