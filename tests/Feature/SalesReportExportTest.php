<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();

        $this->user->companies()->attach($this->company->id);
        $this->actingAs($this->user);
    }

    protected function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => Client::factory()->create()->id,
            'subtotal' => 10,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 10,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_PRESENTIAL,
            'payment_method' => Order::PAYMENT_CASH,
            'confirmed_at' => now(),
            'delivered_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function it_downloads_a_csv_with_the_period_totals()
    {
        $reference = now()->startOfMonth()->addDays(2);

        $this->createOrder(['total_amount' => 150, 'payment_method' => Order::PAYMENT_PIX, 'delivered_at' => $reference]);

        $response = $this->get(route('admin.sales-report.export', [
            'company' => $this->company,
            'period_type' => 'month',
            'month' => $reference->month,
            'year' => $reference->year,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('150.00', $csv);
    }

    #[Test]
    public function it_refuses_export_for_a_company_the_user_cannot_access()
    {
        $otherCompany = Company::factory()->create();

        $response = $this->get(route('admin.sales-report.export', [
            'company' => $otherCompany,
            'period_type' => 'month',
        ]));

        $response->assertForbidden();
    }
}
