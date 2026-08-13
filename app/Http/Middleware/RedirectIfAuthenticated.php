<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($this->redirectPathFor($guard));
            }
        }

        return $next($request);
    }

    /**
     * Cada guard tem sua própria "home" — sem isso, um motorista (ou cliente)
     * já autenticado que abre uma página de guest cai direto no /admin, que
     * exige login separado (guard "web") e o manda pro /admin/login.
     */
    private function redirectPathFor(?string $guard): string
    {
        return match ($guard) {
            'driver' => route('drivers.dashboard'),
            default => RouteServiceProvider::HOME,
        };
    }
}
