<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureMobileDevice
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->isMobile($request)) {
            return $next($request);
        }

        return Inertia::render('Driver/DesktopBlocked', [
            'currentUrl' => $request->fullUrl(),
        ]);
    }

    private function isMobile(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i',
            $userAgent
        );
    }
}
