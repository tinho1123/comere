<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    /**
     * Create payment intent for paying debt.
     * POST /api/companies/{company}/payments/create-intent
     */
    public function createIntent(Request $request, Company $company)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        $request->validate([
            'amount' => 'required|numeric|min:0.50',
            'description' => 'nullable|string|max:255',
        ]);

        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => intval($request->get('amount') * 100), // Convert to cents
                'currency' => 'brl',
                'description' => $request->get('description') ?? "Payment for Comere - {$company->name}",
                'metadata' => [
                    'company_uuid' => $company->uuid,
                    'client_uuid' => $clientUser->uuid,
                ],
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'intent_id' => $paymentIntent->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Stripe createIntent failed', ['company_uuid' => $company->uuid, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível processar o pagamento. Tente novamente.',
            ], 400);
        }
    }

    /**
     * Confirm payment and record transaction.
     * POST /api/companies/{company}/payments/confirm
     */
    public function confirm(Request $request, Company $company)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        $request->validate([
            'intent_id' => 'required|string',
            'amount' => 'required|numeric|min:0.50',
        ]);

        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Retrieve payment intent
            $paymentIntent = PaymentIntent::retrieve($request->get('intent_id'));

            // Verify the intent actually belongs to this client/company — never trust
            // client-submitted amount/ids alone, an attacker could pass any other
            // succeeded intent id to fake a payment for someone else's debt.
            if (($paymentIntent->metadata['client_uuid'] ?? null) !== $clientUser->uuid
                || ($paymentIntent->metadata['company_uuid'] ?? null) !== $company->uuid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment intent does not belong to this client/company',
                ], 403);
            }

            // Verify payment succeeded
            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not completed',
                ], 400);
            }

            // Amount confirmed with Stripe — never trust the client-submitted "amount" for
            // recording/crediting, only what Stripe actually charged.
            $confirmedAmount = $paymentIntent->amount / 100;

            // Record payment in database
            // TODO: Implement payment recording logic using $confirmedAmount

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'amount' => $confirmedAmount,
            ]);
        } catch (\Exception $e) {
            \Log::error('Stripe confirm failed', ['company_uuid' => $company->uuid, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível confirmar o pagamento. Tente novamente.',
            ], 400);
        }
    }

    /**
     * Get payment history.
     * GET /api/companies/{company}/payments
     */
    public function history(Request $request, Company $company)
    {
        $clientUser = auth('sanctum')->user();
        $this->authorizeClientAccess($company, $clientUser);

        // TODO: Implement payment history retrieval
        // This would list all payments made by client for this company

        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }

    /**
     * Verify client has access to company.
     */
    private function authorizeClientAccess(Company $company, $clientUser): void
    {
        if (! $clientUser || ! $clientUser->companies()->where('companies.id', $company->id)->exists()) {
            abort(403, 'Unauthorized access to this company');
        }
    }
}
