<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\SalesReportByPaymentMethod;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesReportByPaymentMethodTest extends TestCase
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

        Filament::setTenant($this->company);
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
    public function it_can_render_with_no_sales()
    {
        $response = $this->get(SalesReportByPaymentMethod::getUrl());

        $response->assertOk();
    }

    #[Test]
    public function it_sums_delivered_orders_by_payment_method_for_the_selected_month()
    {
        $reference = now()->startOfMonth()->addDays(2);

        $this->createOrder(['total_amount' => 100, 'payment_method' => Order::PAYMENT_CASH, 'delivered_at' => $reference]);
        $this->createOrder(['total_amount' => 50, 'payment_method' => Order::PAYMENT_PIX, 'delivered_at' => $reference]);
        $this->createOrder(['total_amount' => 30, 'payment_method' => Order::PAYMENT_DEBIT, 'delivered_at' => $reference]);
        $this->createOrder(['total_amount' => 20, 'payment_method' => Order::PAYMENT_CREDIT, 'delivered_at' => $reference]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'month')
            ->set('month', $reference->month)
            ->set('year', $reference->year)
            ->assertOk()
            ->assertSee('100,00')
            ->assertSee('50,00')
            ->assertSee('30,00')
            ->assertSee('20,00')
            ->assertSee('200,00');
    }

    #[Test]
    public function it_excludes_orders_outside_the_selected_period()
    {
        $reference = now()->startOfMonth()->addDays(2);
        $outsidePeriod = $reference->copy()->addMonths(2);

        $this->createOrder(['total_amount' => 999, 'delivered_at' => $outsidePeriod]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'month')
            ->set('month', $reference->month)
            ->set('year', $reference->year)
            ->assertOk()
            ->assertDontSee('999,00');
    }

    #[Test]
    public function it_excludes_orders_that_are_not_delivered()
    {
        $reference = now()->startOfMonth()->addDays(2);

        $this->createOrder([
            'total_amount' => 777,
            'status' => Order::STATUS_PENDING,
            'delivered_at' => $reference,
        ]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'month')
            ->set('month', $reference->month)
            ->set('year', $reference->year)
            ->assertOk()
            ->assertDontSee('777,00');
    }

    #[Test]
    public function it_excludes_favored_sales_because_they_have_not_been_collected_yet()
    {
        $reference = now()->startOfMonth()->addDays(2);

        $this->createOrder([
            'total_amount' => 456,
            'payment_method' => 'favored',
            'delivered_at' => $reference,
        ]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'month')
            ->set('month', $reference->month)
            ->set('year', $reference->year)
            ->assertOk()
            ->assertDontSee('456,00')
            ->assertSee('0,00');
    }

    #[Test]
    public function it_never_mixes_sales_from_another_company()
    {
        $reference = now()->startOfMonth()->addDays(2);
        $otherCompany = Company::factory()->create();

        Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'client_id' => Client::factory()->create()->id,
            'subtotal' => 321,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 321,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_PRESENTIAL,
            'payment_method' => Order::PAYMENT_CASH,
            'confirmed_at' => $reference,
            'delivered_at' => $reference,
        ]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'month')
            ->set('month', $reference->month)
            ->set('year', $reference->year)
            ->assertOk()
            ->assertDontSee('321,00');
    }

    #[Test]
    public function it_aggregates_the_whole_year_when_switching_period_type()
    {
        $yearStart = now()->startOfYear();

        $this->createOrder(['total_amount' => 60, 'payment_method' => Order::PAYMENT_PIX, 'delivered_at' => $yearStart->copy()->addMonths(1)]);
        $this->createOrder(['total_amount' => 40, 'payment_method' => Order::PAYMENT_PIX, 'delivered_at' => $yearStart->copy()->addMonths(6)]);

        Livewire::test(SalesReportByPaymentMethod::class)
            ->set('periodType', 'year')
            ->set('year', $yearStart->year)
            ->assertOk()
            ->assertSee('100,00');
    }

    #[Test]
    public function it_labels_february_correctly_in_the_yearly_breakdown_when_today_is_a_31st()
    {
        // Regressão: Carbon::createFromFormat('Y-m', '2026-02') herda o dia
        // do "now" real. Se hoje for dia 31 (ou outro dia que fevereiro não
        // tem), o parse passa a transbordar para março, etiquetando a linha
        // errada no fechamento mensal do "Ano completo".
        Carbon::setTestNow(Carbon::create(2026, 1, 31));

        try {
            $this->createOrder([
                'total_amount' => 70,
                'payment_method' => Order::PAYMENT_PIX,
                'delivered_at' => Carbon::create(2026, 2, 15),
            ]);

            $expectedLabel = Carbon::create(2026, 2, 1)->translatedFormat('M/Y');
            $wrongLabel = Carbon::create(2026, 3, 3)->translatedFormat('M/Y');

            Livewire::test(SalesReportByPaymentMethod::class)
                ->set('periodType', 'year')
                ->set('year', 2026)
                ->assertOk()
                ->assertSee($expectedLabel)
                ->assertDontSee($wrongLabel);
        } finally {
            Carbon::setTestNow();
        }
    }
}
