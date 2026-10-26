<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClerkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SSOCallbackController extends Controller
{
    public function __construct(private ClerkService $clerkService) {}

    /**
     * Handle the response from Clerk and sync with local database.
     *
     * The frontend sends only the Clerk session id. The identity (clerk_id,
     * verified email, name) is always re-fetched from Clerk's backend API
     * here, never trusted from request input, otherwise anyone could POST
     * an arbitrary email and log in as that client.
     */
    public function __invoke(Request $request)
    {
        $sessionId = $request->input('session_id');

        if (! is_string($sessionId) || $sessionId === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing session.'], 422);
        }

        $identity = $this->clerkService->verifySession($sessionId);

        if (! $identity) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired session.'], 401);
        }

        $clerkId = $identity['clerk_id'];
        $email = $identity['email'];
        $name = $identity['name'];

        // 1. Verificar se o cliente já existe pelo clerk_id
        $client = Client::where('clerk_id', $clerkId)->first();

        // 2. Se não existir pelo ID, tentar pelo e-mail
        if (! $client && $email) {
            $client = Client::where('email', $email)->first();
            if ($client) {
                $client->update(['clerk_id' => $clerkId]);
            }
        }

        // 3. Se ainda não existir, criar um registro temporário/novo
        // Senha aleatória: o cliente autentica via Clerk, não por e-mail/senha,
        // mas a coluna é NOT NULL; ele pode defini-la depois via "esqueci minha senha".
        if (! $client) {
            $client = Client::create([
                'uuid' => (string) Str::uuid(),
                'clerk_id' => $clerkId,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'active' => true,
                'document_type' => 'cpf',
            ]);
        }

        // 4. LOGAR O CLIENTE NO LARAVEL
        auth()->guard('client')->login($client);

        // 5. Verificar se o CPF está preenchido
        if (empty($client->document_number)) {
            return response()->json([
                'status' => 'incomplete_profile',
                'redirect_url' => route('marketplace.complete-profile'),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('marketplace.index'),
        ]);
    }

    public function completeProfile()
    {
        return Inertia::render('Marketplace/CompleteProfile');
    }

    public function storeProfile(Request $request)
    {
        $request->validate([
            'cpf' => 'required|string|size:11',
        ]);

        $client = auth()->guard('client')->user();

        abort_unless($client !== null, 403);

        $client->update([
            'document_number' => $request->cpf,
            'active' => true,
        ]);

        return redirect()->route('marketplace.index');
    }
}
