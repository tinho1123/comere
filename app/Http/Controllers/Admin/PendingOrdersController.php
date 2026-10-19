<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\TableSessionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingOrdersController extends Controller
{
    public function count(Request $request): JsonResponse
    {
        $company = Company::where('uuid', $request->query('company'))->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $openSessionItems = TableSessionItem::whereHas('session', function ($query) use ($company): void {
            $query->where('company_id', $company->id)->where('status', 'open');
        });

        $count = (clone $openSessionItems)->where('is_delivered', false)->count();

        // latest_item_id tracks every item ever added (delivered or not), so the
        // frontend can detect a new order even if another item was delivered in
        // the same polling interval and the pending count itself didn't rise.
        $latestItemId = (clone $openSessionItems)->max('id');

        // latest_order_id tracks every marketplace order ever created for this
        // company, so the frontend can alert on a new order independently of
        // whatever status it's since moved to.
        $latestOrderId = Order::where('company_id', $company->id)->max('id');
        $pendingOrdersCount = Order::where('company_id', $company->id)
            ->where('status', Order::STATUS_PENDING)
            ->count();

        return response()->json([
            'count' => $count,
            'latest_item_id' => $latestItemId,
            'pending_orders_count' => $pendingOrdersCount,
            'latest_order_id' => $latestOrderId,
        ]);
    }
}
