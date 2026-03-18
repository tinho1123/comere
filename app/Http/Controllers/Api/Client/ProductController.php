<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List all products for a company.
     * GET /api/companies/{company}/products
     */
    public function index(Request $request, Company $company)
    {
        // Verify client has access to company
        $this->authorizeClientAccess($company);

        $query = Product::where('company_id', $company->id)
            ->where('active', true)
            ->where('is_marketplace', true);

        // Filter by category
        if ($request->has('category')) {
            $query->where('category_id', $request->get('category'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Price range filter
        if ($request->has('price_min')) {
            $query->where('total_amount', '>=', $request->get('price_min'));
        }
        if ($request->has('price_max')) {
            $query->where('total_amount', '<=', $request->get('price_max'));
        }

        $products = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get a single product.
     * GET /api/companies/{company}/products/{product}
     */
    public function show(Company $company, Product $product)
    {
        // Verify client has access to company
        $this->authorizeClientAccess($company);

        // Verify product belongs to company
        if ($product->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Get product categories.
     * GET /api/companies/{company}/categories
     */
    public function categories(Company $company)
    {
        // Verify client has access to company
        $this->authorizeClientAccess($company);

        $categories = ProductsCategories::where('company_id', $company->id)
            ->with([
                'products' => function ($query) {
                    $query->where('active', true)->limit(5);
                },
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Verify client has access to company.
     */
    private function authorizeClientAccess(Company $company): void
    {
        $clientUser = auth('sanctum')->user();

        if (! $clientUser || ! $clientUser->companies()->where('companies.id', $company->id)->exists()) {
            abort(403, 'Unauthorized access to this company');
        }
    }
}
