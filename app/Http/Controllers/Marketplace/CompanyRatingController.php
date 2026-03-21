<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRatingController extends Controller
{
    public function store(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $client = auth('client')->user();

        CompanyRating::updateOrCreate(
            ['client_id' => $client->id, 'company_id' => $company->id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        $company->recalculateRating();

        return response()->json([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'average' => $company->fresh()->rating,
            'count' => $company->ratings()->count(),
        ]);
    }
}
