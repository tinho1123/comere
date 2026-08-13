<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DriverAuthController extends Controller
{
    public function showRegister()
    {
        return Inertia::render('Driver/Register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:drivers,phone',
            'cpf' => 'nullable|string|max:14',
            'vehicle_type' => 'required|in:'.Driver::VEHICLE_MOTOBOY.','.Driver::VEHICLE_CAR,
            'password' => 'required|string|min:6|confirmed',
        ]);

        $driver = Driver::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'cpf' => $data['cpf'] ?? null,
            'vehicle_type' => $data['vehicle_type'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'is_online' => false,
        ]);

        Auth::guard('driver')->login($driver);
        $request->session()->regenerate();

        return redirect()->route('drivers.dashboard');
    }

    public function showLogin()
    {
        return Inertia::render('Driver/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::guard('driver')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('drivers.dashboard'));
        }

        throw ValidationException::withMessages([
            'phone' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('driver')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('drivers.login');
    }
}
