<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClientPasswordResetController extends Controller
{
    /**
     * Send a password reset link to the given client email.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('clients')->sendResetLink($request->only('email'));

        return back()->with('success', 'Se o e-mail estiver cadastrado, enviamos um link de redefinição.');
    }

    /**
     * Show the reset password form, reached via the emailed link.
     */
    public function showResetForm(Request $request, string $token)
    {
        return Inertia::render('Marketplace/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Reset the client's password.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('clients')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($client, $password) {
                $client->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('marketplace.index')->with('success', 'Senha redefinida com sucesso. Faça login.');
    }
}
