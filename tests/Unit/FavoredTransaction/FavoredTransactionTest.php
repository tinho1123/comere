<?php

namespace Tests\Unit\FavoredTransaction;

use App\Models\Client;
use App\Models\Company;
use App\Models\FavoredTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoredTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    }

    #[Test]
    public function it_can_create_favored_transaction_with_minimum_data()
    {
        $transactionData = [
            'uuid' => Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Test Transaction',
            'favored_total' => 100.00,
            'quantity' => 1,
            'active' => true,
        ];

        $transaction = FavoredTransaction::create($transactionData);

        $this->assertDatabaseHas('favored_transactions', [
            'name' => 'Test Transaction',
            'client_id' => $this->client->id,
            'favored_total' => 100.00,
        ]);
    }

    #[Test]
    public function it_can_calculate_remaining_balance()
    {
        $transaction = FavoredTransaction::factory()->create([
            'favored_total' => 100.00,
            'favored_paid_amount' => 30.00,
        ]);

        $this->assertEquals(70.00, $transaction->getRemainingBalance());
    }

    #[Test]
    public function it_can_check_if_fully_paid()
    {
        $unpaidTransaction = FavoredTransaction::factory()->create([
            'favored_total' => 100.00,
            'favored_paid_amount' => 80.00,
        ]);

        $paidTransaction = FavoredTransaction::factory()->create([
            'favored_total' => 100.00,
            'favored_paid_amount' => 100.00,
        ]);

        $this->assertFalse($unpaidTransaction->isFullyPaid());
        $this->assertTrue($paidTransaction->isFullyPaid());
    }

    #[Test]
    public function it_uses_uuid_as_route_key()
    {
        $transaction = FavoredTransaction::factory()->create();

        $this->assertEquals('uuid', $transaction->getRouteKeyName());
        $this->assertEquals($transaction->uuid, $transaction->getRouteKey());
    }

    #[Test]
    public function it_belongs_to_client()
    {
        $transaction = FavoredTransaction::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->assertInstanceOf(Client::class, $transaction->client);
        $this->assertEquals($this->client->id, $transaction->client->id);
    }

    #[Test]
    public function it_belongs_to_company()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->assertInstanceOf(Company::class, $transaction->company);
        $this->assertEquals($this->company->id, $transaction->company->id);
    }

    #[Test]
    public function it_can_cast_decimal_fields_correctly()
    {
        $transaction = FavoredTransaction::factory()->create([
            'amount' => '99.99',
            'favored_total' => '199.99',
            'favored_paid_amount' => '50.50',
        ]);

        $this->assertEquals(99.99, $transaction->amount);
        $this->assertEquals(199.99, $transaction->favored_total);
        $this->assertEquals(50.50, $transaction->favored_paid_amount);
        $this->assertIsNumeric($transaction->amount);
    }

    #[Test]
    public function it_filters_by_company_scope()
    {
        // Create transactions for different companies
        $transaction1 = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $otherCompany = Company::factory()->create();
        $transaction2 = FavoredTransaction::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $companyTransactions = FavoredTransaction::where('company_id', $this->company->id)->get();

        $this->assertCount(1, $companyTransactions);
        $this->assertEquals($transaction1->id, $companyTransactions->first()->id);
    }

    #[Test]
    public function it_filters_by_client_relationship()
    {
        $otherClient = Client::factory()->create(['company_id' => $this->company->id]);

        $transaction1 = FavoredTransaction::factory()->create(['client_id' => $this->client->id]);
        $transaction2 = FavoredTransaction::factory()->create(['client_id' => $otherClient->id]);

        $clientTransactions = FavoredTransaction::where('client_id', $this->client->id)->get();

        $this->assertCount(1, $clientTransactions);
        $this->assertEquals($transaction1->id, $clientTransactions->first()->id);
    }

    #[Test]
    public function it_handles_zero_paid_amount()
    {
        $transaction = FavoredTransaction::factory()->create([
            'favored_total' => 100.00,
            'favored_paid_amount' => 0.00,
        ]);

        $this->assertEquals(100.00, $transaction->getRemainingBalance());
        $this->assertFalse($transaction->isFullyPaid());
    }

    #[Test]
    public function it_handles_overpayment_scenario()
    {
        $transaction = FavoredTransaction::factory()->create([
            'favored_total' => 100.00,
            'favored_paid_amount' => 120.00, // Overpayment
        ]);

        $this->assertEquals(-20.00, $transaction->getRemainingBalance());
        $this->assertTrue($transaction->isFullyPaid());
    }
}
