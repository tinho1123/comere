<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\PaymentMethodSummaryWidget;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentMethodSummaryWidgetTest extends TestCase
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

    #[Test]
    public function it_shows_the_current_months_total_by_payment_method()
    {
        Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => Client::factory()->create()->id,
            'subtotal' => 80,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 80,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_PRESENTIAL,
            'payment_method' => Order::PAYMENT_PIX,
            'confirmed_at' => now(),
            'delivered_at' => now(),
        ]);

        Livewire::test(PaymentMethodSummaryWidget::class)
            ->assertOk()
            ->assertSee('R$ 80,00')
            ->assertSee('Pix');
    }
}
