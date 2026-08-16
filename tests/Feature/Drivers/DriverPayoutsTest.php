<?php

namespace Tests\Feature\Drivers;

use App\Filament\Admin\Pages\DriverPayouts;
use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCompany;
use App\Models\DriverPayout;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverPayoutsTest extends TestCase
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

    private function makeDriver(string $phone = '11912345678'): Driver
    {
        return Driver::create([
            'name' => 'Carlos Eduardo',
            'phone' => $phone,
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function linkDriver(Driver $driver, Company $company): void
    {
        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 8,
        ]);
    }

    private function makeUnpaidDelivery(Company $company, Driver $driver, float $fee): Delivery
    {
        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_DELIVERED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => $fee,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => $fee,
        ]);

        return Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_DELIVERED,
            'driver_fee' => $fee,
            'is_paid' => false,
            'dispatched_at' => now()->subHour(),
            'delivered_at' => now(),
        ]);
    }

    #[Test]
    public function it_lists_linked_drivers_with_their_pending_amount()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);
        $driver = $this->makeDriver();
        $this->linkDriver($driver, $company);

        $this->makeUnpaidDelivery($company, $driver, 6.00);
        $this->makeUnpaidDelivery($company, $driver, 7.50);

        Livewire::test(DriverPayouts::class)
            ->assertCanSeeTableRecords([$driver])
            ->assertTableColumnStateSet('pending_total', 13.50, $driver)
            ->assertTableColumnStateSet('pending_count', 2, $driver);
    }

    #[Test]
    public function it_does_not_list_drivers_from_another_company()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        $otherCompany = Company::factory()->create();
        $otherDriver = $this->makeDriver('11999990000');
        $this->linkDriver($otherDriver, $otherCompany);
        $this->makeUnpaidDelivery($otherCompany, $otherDriver, 10);

        Livewire::test(DriverPayouts::class)
            ->assertCanNotSeeTableRecords([$otherDriver]);
    }

    #[Test]
    public function it_confirms_a_payout_marking_selected_deliveries_as_paid()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);
        $driver = $this->makeDriver();
        $this->linkDriver($driver, $company);

        $delivery1 = $this->makeUnpaidDelivery($company, $driver, 6.00);
        $delivery2 = $this->makeUnpaidDelivery($company, $driver, 7.50);

        Livewire::test(DriverPayouts::class)
            ->callTableAction('settle', $driver)
            ->assertCanSeeTableRecords([$delivery1, $delivery2])
            ->callTableBulkAction('confirm_payout', [$delivery1, $delivery2], data: [
                'method' => DriverPayout::METHOD_PIX,
                'notes' => 'Pago em mãos',
            ]);

        $this->assertDatabaseHas('driver_payouts', [
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'total_amount' => 13.50,
            'method' => DriverPayout::METHOD_PIX,
        ]);

        $payout = DriverPayout::firstOrFail();

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery1->id,
            'is_paid' => true,
            'driver_payout_id' => $payout->id,
        ]);
        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery2->id,
            'is_paid' => true,
            'driver_payout_id' => $payout->id,
        ]);
    }

    #[Test]
    public function paid_deliveries_no_longer_appear_pending_for_the_driver()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);
        $driver = $this->makeDriver();
        $this->linkDriver($driver, $company);

        $delivery = $this->makeUnpaidDelivery($company, $driver, 6.00);

        Livewire::test(DriverPayouts::class)
            ->callTableAction('settle', $driver)
            ->callTableBulkAction('confirm_payout', [$delivery], data: [
                'method' => DriverPayout::METHOD_CASH,
            ]);

        Livewire::test(DriverPayouts::class)
            ->assertTableColumnStateSet('pending_total', null, $driver);
    }
}
