<?php

namespace Tests\Feature\Orders;

use App\Filament\Admin\Resources\OrderResource\Widgets\FailedDeliveriesAlertWidget;
use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FailedDeliveriesAlertWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCompanyAdmin(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        return $user;
    }

    #[Test]
    public function it_renders_without_a_root_tag_when_there_are_no_failed_deliveries()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        // Regressão: a view do widget ficava vazia (sem tag raiz) quando count
        // era 0, o que o Livewire não consegue renderizar e derruba qualquer
        // página que inclua o widget com "RootTagMissingFromViewException".
        Livewire::test(FailedDeliveriesAlertWidget::class)
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_the_alert_when_there_is_a_shipped_order_with_a_failed_delivery()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_SHIPPED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 30,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 30,
        ]);

        $driver = Driver::create([
            'name' => 'Carlos Eduardo',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_FAILED,
            'driver_fee' => 8,
            'dispatched_at' => now(),
        ]);

        Livewire::test(FailedDeliveriesAlertWidget::class)
            ->assertSuccessful()
            ->assertSee('1 entrega com problema precisa');
    }
}
