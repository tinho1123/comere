<?php

namespace Tests\Feature\FavoredTransaction;

use App\Models\Client;
use App\Models\Company;
use App\Models\FavoredTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoredTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->client = Client::factory()->create(['company_id' => $this->company->id]);

        // Associate user with company
        $this->user->companies()->attach($this->company->id);
    }

    /** @test */
    public function it_can_list_favored_transactions()
    {
        Sanctum::actingAs($this->user);

        FavoredTransaction::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->getJson('/api/favored-transactions');

        $response->assertOk()
            ->assertJsonCount(3, 'transactions')
            ->assertJsonStructure([
                'client',
                'transactions' => [
                    '*' => [
                        'id',
                        'uuid',
                        'name',
                        'favored_total',
                        'favored_paid_amount',
                        'quantity',
                        'client' => [
                            'id',
                            'name',
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_can_list_favored_transactions_for_specific_client()
    {
        Sanctum::actingAs($this->user);

        $client1 = Client::factory()->create(['company_id' => $this->company->id]);
        $client2 = Client::factory()->create(['company_id' => $this->company->id]);

        FavoredTransaction::factory()->count(2)->create(['client_id' => $client1->id, 'company_id' => $this->company->id]);
        FavoredTransaction::factory()->count(3)->create(['client_id' => $client2->id, 'company_id' => $this->company->id]);

        $response = $this->getJson("/api/favored-transactions/{$client1->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'transactions')
            ->assertJsonPath('client.uuid', $client1->uuid);
    }

    /** @test */
    public function it_can_create_favored_transaction()
    {
        Sanctum::actingAs($this->user);

        $transactionData = [
            'client_id' => $this->client->id,
            'name' => 'Test API Transaction',
            'description' => 'Created via API',
            'favored_total' => 150.00,
            'quantity' => 2,
        ];

        $response = $this->postJson('/api/favored-transactions', $transactionData);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Transação de fiado criada com sucesso',
            ]);

        $this->assertDatabaseHas('favored_transactions', [
            'name' => 'Test API Transaction',
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'favored_total' => 150.00,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/favored-transactions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'client_id',
                'name',
                'favored_total',
                'quantity',
            ]);
    }

    /** @test */
    public function it_validates_client_exists_on_create()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/favored-transactions', [
            'client_id' => 999999,
            'name' => 'Test Transaction',
            'favored_total' => 100.00,
            'quantity' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    }

    /** @test */
    public function it_can_update_favored_transaction()
    {
        Sanctum::actingAs($this->user);

        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $updateData = [
            'name' => 'Updated Transaction',
            'favored_total' => 250.00,
            'favored_paid_amount' => 50.00,
        ];

        $response = $this->putJson("/api/favored-transactions/{$transaction->uuid}", $updateData);

        $response->assertOk()
            ->assertJson([
                'message' => 'Transação de fiado atualizada com sucesso',
            ]);

        $this->assertDatabaseHas('favored_transactions', [
            'id' => $transaction->id,
            'name' => 'Updated Transaction',
            'favored_total' => 250.00,
            'favored_paid_amount' => 50.00,
        ]);
    }

    /** @test */
    public function it_can_delete_favored_transaction()
    {
        Sanctum::actingAs($this->user);

        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson("/api/favored-transactions/{$transaction->uuid}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Transação de fiado removida com sucesso',
            ]);

        $this->assertDatabaseMissing('favored_transactions', [
            'id' => $transaction->id,
        ]);
    }

    /** @test */
    public function it_can_register_payment()
    {
        Sanctum::actingAs($this->user);

        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'favored_total' => 100.00,
            'favored_paid_amount' => 20.00,
        ]);

        $paymentData = [
            'amount' => 30.00,
        ];

        $response = $this->postJson("/api/favored-transactions/{$transaction->uuid}/pay", $paymentData);

        $response->assertOk()
            ->assertJson([
                'message' => 'Pagamento registrado com sucesso',
                'remaining_balance' => 50.00,
            ]);

        $this->assertDatabaseHas('favored_transactions', [
            'id' => $transaction->id,
            'favored_paid_amount' => 50.00,
        ]);
    }

    /** @test */
    public function it_validates_payment_amount_does_not_exceed_balance()
    {
        Sanctum::actingAs($this->user);

        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'favored_total' => 100.00,
            'favored_paid_amount' => 80.00,
        ]);

        $paymentData = [
            'amount' => 30.00, // More than remaining balance (20.00)
        ];

        $response = $this->postJson("/api/favored-transactions/{$transaction->uuid}/pay", $paymentData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_can_list_clients_with_transactions()
    {
        Sanctum::actingAs($this->user);

        $client1 = Client::factory()->create(['company_id' => $this->company->id]);
        $client2 = Client::factory()->create(['company_id' => $this->company->id]);

        FavoredTransaction::factory()->count(2)->create([
            'client_id' => $client1->id,
            'company_id' => $this->company->id,
            'favored_total' => 100.00,
            'favored_paid_amount' => 20.00,
        ]);

        FavoredTransaction::factory()->count(1)->create([
            'client_id' => $client2->id,
            'company_id' => $this->company->id,
            'favored_total' => 50.00,
            'favored_paid_amount' => 50.00,
        ]);

        $response = $this->getJson('/api/favored-transactions/clients-with-transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'clients')
            ->assertJsonStructure([
                'clients' => [
                    '*' => [
                        'id',
                        'uuid',
                        'name',
                        'transaction_count',
                        'total_debt',
                        'paid_amount',
                    ],
                ],
                'total',
            ]);
    }

    /** @test */
    public function it_filters_transactions_by_company()
    {
        // Create transaction for our company
        $ourTransaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Create transaction for another company
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->companies()->attach($otherCompany->id);

        $otherTransaction = FavoredTransaction::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson('/api/favored-transactions');

        $response->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.id', $otherTransaction->id);
    }

    /** @test */
    public function it_handles_transaction_not_found()
    {
        Sanctum::actingAs($this->user);

        $nonExistentUuid = Str::uuid();

        $response = $this->getJson("/api/favored-transactions/{$nonExistentUuid}");

        $response->assertNotFound();
    }

    /** @test */
    public function it_handles_unauthorized_access()
    {
        $response = $this->getJson('/api/favored-transactions');

        $response->assertUnauthorized();
    }
}
