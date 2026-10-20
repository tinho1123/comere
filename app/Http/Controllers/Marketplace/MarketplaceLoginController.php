<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceLoginController extends Controller
{
    /**
     * Handle a direct client registration for the marketplace.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clients,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'document_number' => ['required', 'string', 'size:11', 'unique:clients,document_number'],
        ]);

        $client = Client::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'document_type' => 'cpf',
            'document_number' => $validated['document_number'],
            'active' => true,
        ]);

        Auth::guard('client')->login($client);

        $request->session()->regenerate();

        return redirect()->intended(route('marketplace.index'));
    }

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
                'email' => __('auth.throttle', ['seconds' => (int) ceil(now()->diffInSeconds($client->locked_until))]),
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
