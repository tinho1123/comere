<?php

namespace Tests\Feature\FavoredTransaction;

use App\Models\Company;
use App\Models\FavoredTransaction;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemindFavoredDueDatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_the_company_about_unpaid_debts_due_tomorrow()
    {
        $company = Company::factory()->create();

        FavoredTransaction::factory()->create([
            'company_id' => $company->id,
            'active' => true,
            'due_date' => now()->addDay(),
            'favored_total' => 100,
            'favored_paid_amount' => 0,
        ]);

        FavoredTransaction::factory()->create([
            'company_id' => $company->id,
            'active' => true,
            'due_date' => now()->addDay(),
            'favored_total' => 50,
            'favored_paid_amount' => 50,
        ]);

        FavoredTransaction::factory()->create([
            'company_id' => $company->id,
            'active' => true,
            'due_date' => now()->addDays(5),
            'favored_total' => 100,
            'favored_paid_amount' => 0,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) use ($company) {
            $mock->shouldReceive('notifyCompany')
                ->once()
                ->with(
                    $company->id,
                    '⏰ Fiado vencendo amanhã',
                    \Mockery::on(fn (string $body) => str_contains($body, '1 fiado vence') && str_contains($body, '100,00')),
                    \Mockery::any()
                );
        });

        $this->artisan('favored:remind-due-dates')->assertSuccessful();
    }

    #[Test]
    public function it_does_not_notify_when_nothing_is_due_tomorrow()
    {
        $company = Company::factory()->create();

        FavoredTransaction::factory()->create([
            'company_id' => $company->id,
            'active' => true,
            'due_date' => now()->addDays(10),
            'favored_total' => 100,
            'favored_paid_amount' => 0,
        ]);

        $this->mock(PushNotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('notifyCompany');
        });

        $this->artisan('favored:remind-due-dates')->assertSuccessful();
    }
}
