<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\DistanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');
        $distanceService = new DistanceService;
        $client = auth()->guard('client')->user();
        $defaultAddress = $client?->addresses()->where('is_default', true)->first();

        $companies = Company::where('active', true)
            ->with('deliveryFeeRanges')
            ->when($category, fn ($q, $cat) => $q->where('type', $cat))
            ->get()
            ->map(function ($company) use ($distanceService, $defaultAddress) {
                $distance = null;
                $deliveryFee = null;

                if ($defaultAddress && $distanceService->canCalculate(
                    $defaultAddress->latitude, $defaultAddress->longitude,
                    $company->latitude, $company->longitude
                )) {
                    $distance = $distanceService->calculate(
                        $defaultAddress->latitude, $defaultAddress->longitude,
                        $company->latitude, $company->longitude
                    );
                    $deliveryFee = $distanceService->getFeeForDistance($distance, $company->deliveryFeeRanges);
                }

                return [
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                    'type' => $company->type,
                    'logo' => $company->logo_path ? Storage::url($company->logo_path) : '/default-store-logo.png',
                    'banner' => $company->banner_path ? Storage::url($company->banner_path) : '/default-store-banner.png',
                    'rating' => $company->rating,
                    'delivery_time' => $company->delivery_time ?? '20-30 min',
                    'is_promoted' => $company->is_promoted,
                    'distance_km' => $distance,
                    'delivery_fee' => $deliveryFee,
                    'has_address' => $company->latitude !== null,
                ];
            });

        $promotedProducts = Product::whereHas('company', fn ($q) => $q->where('active', true))
            ->where('active', true)
            ->where('is_marketplace', true)
            ->where('discounts', '>', 0)
            ->limit(8)
            ->get();

        $lastVisitedUuids = session()->get('last_visited_stores', []);
        $lastVisited = Company::whereIn('uuid', $lastVisitedUuids)
            ->get()
            ->sortBy(fn ($c) => array_search($c->uuid, $lastVisitedUuids))
            ->map(fn ($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'logo' => $c->logo_path ? Storage::url($c->logo_path) : '/default-store-logo.png',
                'type' => $c->type,
            ]);

        return Inertia::render('Marketplace/Index', [
            'companies' => $companies,
            'promotedProducts' => $promotedProducts,
            'lastVisited' => $lastVisited,
            'selectedCategory' => $category,
            'categories' => ['Restaurantes', 'Mercados', 'Farmácias', 'Bebidas'],
        ]);
    }

    public function show(Company $company)
    {
        // Registrar visita
        $lastVisited = session()->get('last_visited_stores', []);
        $lastVisited = array_diff($lastVisited, [$company->uuid]); // Remover se já existe
        array_unshift($lastVisited, $company->uuid); // Adicionar no início
        $lastVisited = array_slice($lastVisited, 0, 5); // Manter as últimas 5
        session()->put('last_visited_stores', $lastVisited);

        $company->load([
            'products' => fn ($q) => $q->where('active', true)->where('is_marketplace', true)->with('category'),
            'deliveryFeeRanges',
        ]);

        $distanceService = new DistanceService;
        $client = auth()->guard('client')->user();
        $defaultAddress = $client?->addresses()->where('is_default', true)->first();

        $distance = null;
        $deliveryFee = null;

        if ($defaultAddress && $distanceService->canCalculate(
            $defaultAddress->latitude, $defaultAddress->longitude,
            $company->latitude, $company->longitude
        )) {
            $distance = $distanceService->calculate(
                $defaultAddress->latitude, $defaultAddress->longitude,
                $company->latitude, $company->longitude
            );
            $deliveryFee = $distanceService->getFeeForDistance($distance, $company->deliveryFeeRanges);
        }

        return Inertia::render('Marketplace/Show', [
            'company' => [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'description' => $company->description,
                'type' => $company->type,
                'logo' => $company->logo_path ? Storage::url($company->logo_path) : '/default-store-logo.png',
                'banner' => $company->banner_path ? Storage::url($company->banner_path) : '/default-store-banner.png',
                'rating' => $company->rating,
                'delivery_time' => $company->delivery_time ?? '20-30 min',
                'distance_km' => $distance,
                'delivery_fee' => $deliveryFee,
                'fee_ranges' => $company->deliveryFeeRanges->map(fn ($r) => [
                    'max_km' => $r->max_km,
                    'fee' => $r->fee,
                    'is_active' => $r->is_active,
                ]),
            ],
            'productsByCategory' => $company->products
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'description' => $product->description,
                    'amount' => $product->amount,
                    'discounts' => $product->discounts,
                    'isCool' => $product->isCool,
                    'image' => $product->image ? Storage::url($product->image) : null,
                    'is_for_favored' => $product->is_for_favored,
                    'favored_price' => $product->favored_price,
                    'category' => $product->category?->name,
                ])
                ->groupBy('category'),
        ]);
    }

    public function orders()
    {
        $orders = Order::where('client_id', auth()->guard('client')->id())
            ->with(['items', 'company'])
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'uuid' => $order->uuid,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'company' => [
                    'name' => $order->company->name,
                    'logo' => $order->company->logo_path ? Storage::url($order->company->logo_path) : '/default-store-logo.png',
                ],
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'total_amount' => $item->total_amount,
                ]),
            ]);

        return Inertia::render('Marketplace/Orders', [
            'orders' => $orders,
        ]);
    }

    public function storeOrder(Request $request, Company $company)
    {
        $client = auth('client')->user();

        if (! $client->companies()->where('companies.id', $company->id)->exists()) {
            $client->companies()->attach($company->id, ['is_active' => true]);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order = Order::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'notes' => $request->notes,
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        $subtotal = 0;

        foreach ($request->items as $item) {
            $product = Product::where('id', $item['product_id'])
                ->where('company_id', $company->id)
                ->firstOrFail();

            $itemTotal = $product->amount * $item['quantity'];

            OrderItem::create([
                'uuid' => Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $item['quantity'],
                'unit_price' => $product->amount,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'total_amount' => $itemTotal,
            ]);

            $subtotal += $itemTotal;
        }

        $order->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
        ]);

        return redirect()->route('marketplace.orders');
    }
}
