<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');

        $companies = Company::where('active', true)
            ->when($category, function ($query, $category) {
                return $query->where('type', $category);
            })
            ->get()
            ->map(fn ($company) => [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'type' => $company->type,
                'logo' => $company->logo_path ?? '/default-store-logo.png',
                'banner' => $company->banner_path ?? '/default-store-banner.png',
                'rating' => $company->rating,
                'delivery_time' => $company->delivery_time ?? '20-30 min',
                'is_promoted' => $company->is_promoted,
            ]);

        $promotedProducts = Product::whereHas('company', fn ($q) => $q->where('active', true))
            ->where('discounts', '>', 0) // Usando a coluna correta 'discounts'
            ->limit(8)
            ->get();

        $lastVisitedUuids = session()->get('last_visited_stores', []);
        $lastVisited = Company::whereIn('uuid', $lastVisitedUuids)
            ->get()
            ->sortBy(fn ($c) => array_search($c->uuid, $lastVisitedUuids));

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

        $company->load(['products' => fn ($q) => $q->where('active', true)->with('category')]);

        return Inertia::render('Marketplace/Show', [
            'company' => [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'description' => $company->description,
                'type' => $company->type,
                'logo' => $company->logo_path ?? '/default-store-logo.png',
                'banner' => $company->banner_path ?? '/default-store-banner.png',
                'rating' => $company->rating,
                'delivery_time' => $company->delivery_time ?? '20-30 min',
            ],
            'productsByCategory' => $company->products->groupBy('category.name'),
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
                    'logo' => $order->company->logo_path ?? '/default-store-logo.png',
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
}
