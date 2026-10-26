<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClerkService
{
    /**
     * Verify a Clerk session server-side and return the verified identity.
     *
     * The frontend only ever hands us a session id; the user id, email and
     * name used to log a Client in are always re-fetched here from Clerk's
     * API with our secret key, never trusted from the request body. This is
     * what stops a forged POST to /sso-callback from impersonating any
     * client by email.
     *
     * @return array{clerk_id: string, email: ?string, name: ?string}|null
     */
    public function verifySession(string $sessionId): ?array
    {
        $secretKey = config('services.clerk.secret_key');

        if (! $secretKey || ! $sessionId) {
            return null;
        }

        $sessionResponse = Http::withToken($secretKey)
            ->get("https://api.clerk.com/v1/sessions/{$sessionId}");

        if (! $sessionResponse->successful()) {
            Log::warning('Clerk session verification failed', ['status' => $sessionResponse->status()]);

            return null;
        }

        $session = $sessionResponse->json();

        if (($session['status'] ?? null) !== 'active' || empty($session['user_id'])) {
            return null;
        }

        $userId = $session['user_id'];

        $userResponse = Http::withToken($secretKey)
            ->get("https://api.clerk.com/v1/users/{$userId}");

        if (! $userResponse->successful()) {
            Log::warning('Clerk user lookup failed', ['status' => $userResponse->status()]);

            return null;
        }

        $user = $userResponse->json();

        $email = null;
        $primaryEmailId = $user['primary_email_address_id'] ?? null;

        foreach ($user['email_addresses'] ?? [] as $emailAddress) {
            $isPrimary = $primaryEmailId && $emailAddress['id'] === $primaryEmailId;
            $isVerified = ($emailAddress['verification']['status'] ?? null) === 'verified';

            if ($isPrimary && $isVerified) {
                $email = $emailAddress['email_address'];
                break;
            }
        }

        $name = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: null;

        return [
            'clerk_id' => $userId,
            'email' => $email,
            'name' => $name,
        ];
    }
}
