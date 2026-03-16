<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * List orders for client.
     * GET /api/companies/{company}/orders
     */
    public function index(Request $request, Company $company)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        $query = Order::where('company_id', $company->id)
            ->where('client_id', $clientUser->client_id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $orders = $query->with('items.product')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get a single order.
     * GET /api/companies/{company}/orders/{order}
     */
    public function show(Company $company, Order $order)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        // Verify order belongs to client and company
        if ($order->company_id !== $company->id || $order->client_id !== $clientUser->client_id) {
            abort(403, 'Unauthorized access to this order');
        }

        $order->load('items.product');

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Create a new order.
     * POST /api/companies/{company}/orders
     */
    public function store(Request $request, Company $company)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Create order
        $order = Order::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $clientUser->client_id,
            'status' => 'pending',
            'notes' => $request->get('notes'),
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        $subtotal = 0;

        // Add items
        foreach ($request->get('items') as $item) {
            $product = Product::find($item['product_id']);

            if ($product->company_id !== $company->id) {
                abort(403, 'Product does not belong to this company');
            }

            $discountPercent = $item['discount_percent'] ?? 0;
            $itemTotal = $this->calculateItemTotal(
                $product->total_amount,
                $item['quantity'],
                $discountPercent
            );

            OrderItem::create([
                'uuid' => Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $item['quantity'],
                'unit_price' => $product->total_amount,
                'discount_percent' => $discountPercent,
                'discount_amount' => ($product->total_amount * $item['quantity']) * ($discountPercent / 100),
                'total_amount' => $itemTotal,
            ]);

            $subtotal += $itemTotal;
        }

        // Update order totals
        $order->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
        ]);

        $order->load('items.product');

        // Notify company admin via push notification
        try {
            $client = $clientUser->client ?? null;
            $clientName = $client?->name ?? 'Um cliente';
            $itemCount = $order->items->count();

            app(PushNotificationService::class)->notifyCompany(
                $company->id,
                '🛒 Novo pedido recebido!',
                "{$clientName} fez um pedido com {$itemCount} ".($itemCount === 1 ? 'item' : 'itens').'.',
                "/admin/{$company->uuid}/orders"
            );
        } catch (\Throwable) {
            // Never fail the order creation due to notification errors
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    /**
     * Calculate item total with discount.
     */
    private function calculateItemTotal(float $unitPrice, int $quantity, float $discountPercent): float
    {
        $subtotal = $unitPrice * $quantity;
        $discount = $subtotal * ($discountPercent / 100);

        return $subtotal - $discount;
    }

    /**
     * Verify client has access to company.
     */
    private function authorizeClientAccess(Company $company, $clientUser): void
    {
        if (! $clientUser || ! $clientUser->companies()->where('companies.id', $company->id)->exists()) {
            abort(403, 'Unauthorized access to this company');
        }
    }
}
