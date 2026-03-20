<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FavoredTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FavoredTransactionController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = auth()->user()->companies->first()->id;

        $transactions = FavoredTransaction::with(['client', 'product', 'category'])
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'client' => null,
            'transactions' => $transactions,
            'total_debt' => $transactions->sum('favored_total'),
            'total_paid' => $transactions->sum('favored_paid_amount'),
            'total_items' => $transactions->sum('quantity'),
        ]);
    }

    public function showByClient(Client $client): JsonResponse
    {
        $companyId = auth()->user()->companies->first()->id;

        $transactions = FavoredTransaction::with(['client'])
            ->where('client_id', $client->id)
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'client' => $client->only(['uuid', 'name', 'email', 'phone', 'active']),
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'favored_total' => 'required|numeric|min:0',
            'favored_paid_amount' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:products_categories,id',
        ]);

        $validated['uuid'] = Str::uuid();
        $validated['company_id'] = auth()->user()->companies->first()->id;
        $validated['active'] = true;

        $transaction = FavoredTransaction::create($validated);

        return response()->json([
            'message' => 'Transação de fiado criada com sucesso',
            'transaction' => $transaction,
        ], 201);
    }

    public function update(Request $request, FavoredTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->company_id === auth()->user()->companies->first()->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'favored_total' => 'required|numeric|min:0',
            'favored_paid_amount' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'image' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:products_categories,id',
            'active' => 'nullable|boolean',
        ]);

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transação de fiado atualizada com sucesso',
            'transaction' => $transaction->fresh(),
        ]);
    }

    public function destroy(FavoredTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->company_id === auth()->user()->companies->first()->id, 403);

        $transaction->delete();

        return response()->json([
            'message' => 'Transação de fiado removida com sucesso',
        ]);
    }

    public function payDebt(Request $request, FavoredTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->company_id === auth()->user()->companies->first()->id, 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.$transaction->getRemainingBalance(),
        ]);

        $newPaidAmount = $transaction->favored_paid_amount + $validated['amount'];
        $transaction->update(['favored_paid_amount' => $newPaidAmount]);

        return response()->json([
            'message' => 'Pagamento registrado com sucesso',
            'transaction' => $transaction->fresh(),
            'remaining_balance' => $transaction->fresh()->getRemainingBalance(),
        ]);
    }

    public function getClientsWithTransactions(): JsonResponse
    {
        $companyId = auth()->user()->companies->first()->id;

        $clients = DB::table('clients as c')
            ->join('favored_transactions as ft', function ($join) use ($companyId) {
                $join->on('c.id', '=', 'ft.client_id')
                    ->where('ft.company_id', '=', $companyId);
            })
            ->select([
                'c.id',
                'c.uuid',
                'c.name',
                'c.email',
                'c.phone',
                'c.active',
                DB::raw('COUNT(ft.id) as transaction_count'),
                DB::raw('SUM(ft.favored_total) as total_debt'),
                DB::raw('SUM(ft.favored_paid_amount) as paid_amount'),
            ])
            ->groupBy('c.id', 'c.uuid', 'c.name', 'c.email', 'c.phone', 'c.active')
            ->orderBy('c.name')
            ->get();

        return response()->json([
            'clients' => $clients,
            'total' => $clients->count(),
        ]);
    }
}
