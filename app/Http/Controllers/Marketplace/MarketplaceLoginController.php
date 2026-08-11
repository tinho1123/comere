<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MarketplaceLoginController extends Controller
{
    /**
     * Handle a manual login request for the marketplace.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $client = Client::where('email', $credentials['email'])->first();

        if ($client && ! $client->validateLoginAttempts()) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => now()->diffInSeconds($client->locked_until)]),
            ]);
        }

        if (Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $client?->resetLoginAttempts();

            return redirect()->intended(route('marketplace.index'));
        }

        $client?->incrementLoginAttempts();

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * Log the client out of the application.
     */
    public function logout(Request $request)
    {
        Auth::guard('client')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketplace.index');
    }
}
