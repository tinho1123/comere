<?php

namespace Tests\Feature\FavoredTransaction;

use App\Models\Client;
use App\Models\Company;
use App\Models\FavoredTransaction;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoredTransactionPaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_the_company_when_a_debt_payment_is_registered_via_the_api()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'favored_total' => 100,
            'favored_paid_amount' => 0,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) use ($company) {
            $mock->shouldReceive('notifyCompany')
                ->once()
                ->with(
                    $company->id,
                    '💰 Pagamento de fiado registrado',
                    \Mockery::on(fn (string $body) => str_contains($body, '40,00')),
                    \Mockery::any()
                );
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/favored-transactions/{$transaction->uuid}/pay", ['amount' => 40]);

        $response->assertOk();
    }

    #[Test]
    public function it_does_not_notify_when_an_unrelated_field_changes()
    {
        $transaction = FavoredTransaction::factory()->create([
            'favored_total' => 100,
            'favored_paid_amount' => 0,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notifyCompany');
        });

        $transaction->update(['description' => 'Nova descrição']);
    }
}
