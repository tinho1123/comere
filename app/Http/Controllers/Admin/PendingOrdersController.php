<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TableSessionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingOrdersController extends Controller
{
    public function count(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $count = TableSessionItem::where('is_delivered', false)
            ->whereHas('session', function ($query) use ($company): void {
                $query->where('company_id', $company->id)->where('status', 'open');
            })
            ->count();

        return response()->json(['count' => $count]);
    }
}
