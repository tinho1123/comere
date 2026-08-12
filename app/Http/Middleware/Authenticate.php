<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated,
     * based on which guard the route required.
     */
    protected function unauthenticated($request, array $guards)
    {
        $redirectRouteName = match ($guards[0] ?? null) {
            'client' => 'marketplace.index',
            'driver' => 'motoboy.login.show',
            default => 'login',
        };

        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            $request->expectsJson() ? null : route($redirectRouteName),
        );
    }
}
