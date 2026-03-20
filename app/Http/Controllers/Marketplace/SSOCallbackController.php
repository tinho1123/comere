<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SSOCallbackController extends Controller
{
    /**
     * Handle the response from Clerk and sync with local database.
     */
    public function __invoke(Request $request)
    {
        // Esta rota será acessada pelo React após o Clerk autenticar
        // O frontend enviará os dados do usuário do Clerk
        $clerkId = $request->input('clerk_id');
        $email = $request->input('email');
        $name = $request->input('name');

        if (! $clerkId) {
            return redirect()->route('marketplace.index');
        }

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
        if (! $client) {
            $client = Client::create([
                'uuid' => (string) Str::uuid(),
                'clerk_id' => $clerkId,
                'name' => $name,
                'email' => $email,
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
