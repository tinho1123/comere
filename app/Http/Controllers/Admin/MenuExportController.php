<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;

class MenuExportController extends Controller
{
    public function show(Company $company)
    {
        abort_unless(
            auth()->user()->companies()->where('companies.id', $company->id)->exists(),
            403
        );

        $categories = Product::where('company_id', $company->id)
            ->where('active', true)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Product $product) => $product->category->name ?? 'Outros')
            ->sortKeys();

        return view('admin.products.menu-print', [
            'company' => $company,
            'categories' => $categories,
        ]);
    }
}
