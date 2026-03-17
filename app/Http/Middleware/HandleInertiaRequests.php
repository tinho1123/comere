<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = [
            ...parent::share($request),
            'auth' => [
                'user' => auth()->guard('client')->user(),
            ],
            'orders_count' => auth()->guard('client')->check()
                ? [
                    'unfinished' => Order::where('client_id', auth()->guard('client')->id())
                        ->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED])
                        ->count(),
                ] : [
                    'unfinished' => 0,
                ],
            'default_address' => auth()->guard('client')->check()
                ? auth()->guard('client')->user()->addresses()->where('is_default', true)->first()
                : null,
        ];

        \Log::info('Inertia Shared Data:', [
            'client_logged_in' => auth()->guard('client')->check(),
            'client_id' => auth()->guard('client')->id(),
            'url' => $request->fullUrl(),
        ]);

        return $shared;
    }
}
