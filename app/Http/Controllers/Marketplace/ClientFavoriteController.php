<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class ClientFavoriteController extends Controller
{
    public function toggle(Company $company): JsonResponse
    {
        $client = auth('client')->user();

        $exists = $client->favoriteCompanies()->where('company_id', $company->id)->exists();

        if ($exists) {
            $client->favoriteCompanies()->detach($company->id);
            $favorited = false;
        } else {
            $client->favoriteCompanies()->attach($company->id);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }
}
