<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Table as TableModel;
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

        return view('table.show', ['table' => $table]);
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
}
