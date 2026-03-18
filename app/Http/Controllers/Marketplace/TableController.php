<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Table as TableModel;
use App\Models\TableSessionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableController extends Controller
{
    public function show(string $uuid): View
    {
        $table = TableModel::where('uuid', $uuid)
            ->with(['activeSession.items.product', 'activeSession.client', 'company'])
            ->firstOrFail();

        $products = Product::where('company_id', $table->company_id)
            ->where('active', true)
            ->where('quantity', '>', 0)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => $p->category?->name ?? 'Outros');

        return view('table.show', ['table' => $table, 'products' => $products]);
    }

    public function registerName(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'guest_name' => ['required', 'string', 'max:100'],
        ]);

        $table = TableModel::where('uuid', $uuid)->firstOrFail();
        $session = $table->activeSession;

        if ($session && $session->isOpen() && ! $session->client_id && ! $session->guest_name) {
            $session->update(['guest_name' => $request->guest_name]);
        }

        return redirect()->route('table.show', $uuid)->with('success', 'Nome registrado com sucesso!');
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
}
