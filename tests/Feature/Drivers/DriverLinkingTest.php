<?php

namespace Tests\Feature\Drivers;

use App\Filament\Admin\Resources\DriverResource\Pages\ManageDrivers;
use App\Filament\Admin\Resources\OrderResource\Pages\ManageOrders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverCompany;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverLinkingTest extends TestCase
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
            'name' => 'Motoboy Teste',
            'phone' => $phone,
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_can_invite_an_existing_driver_by_phone()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);
        $driver = $this->makeDriver();

        Livewire::test(ManageDrivers::class)
            ->callTableAction('invite', data: [
                'phone' => $driver->phone,
                'delivery_fee' => 12.5,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('driver_company', [
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_PENDING,
            'delivery_fee' => 12.5,
        ]);
    }

    #[Test]
    public function inviting_an_unregistered_phone_does_not_create_a_link()
    {
        $company = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        Livewire::test(ManageDrivers::class)
            ->callTableAction('invite', data: [
                'phone' => '11900000000',
                'delivery_fee' => 10,
            ]);

        $this->assertDatabaseCount('driver_company', 0);
    }

    #[Test]
    public function driver_can_accept_a_pending_invite()
    {
        $company = Company::factory()->create();
        $driver = $this->makeDriver();
        $link = DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_PENDING,
            'delivery_fee' => 10,
        ]);

        $response = $this->actingAs($driver, 'driver')
            ->post(route('motoboy.invite.accept', $link));

        $response->assertRedirect(route('motoboy.dashboard'));
        $this->assertEquals(Driver::LINK_ACCEPTED, $link->fresh()->status);
        $this->assertTrue($driver->isLinkedTo($company));
    }

    #[Test]
    public function driver_can_reject_a_pending_invite()
    {
        $company = Company::factory()->create();
        $driver = $this->makeDriver();
        $link = DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_PENDING,
            'delivery_fee' => 10,
        ]);

        $this->actingAs($driver, 'driver')
            ->post(route('motoboy.invite.reject', $link));

        $this->assertEquals(Driver::LINK_REJECTED, $link->fresh()->status);
        $this->assertFalse($driver->isLinkedTo($company));
    }

    #[Test]
    public function a_driver_cannot_respond_to_someone_elses_invite()
    {
        $company = Company::factory()->create();
        $driver = $this->makeDriver('11911111111');
        $otherDriver = $this->makeDriver('11922222222');
        $link = DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_PENDING,
            'delivery_fee' => 10,
        ]);

        $response = $this->actingAs($otherDriver, 'driver')
            ->post(route('motoboy.invite.accept', $link));

        $response->assertForbidden();
        $this->assertEquals(Driver::LINK_PENDING, $link->fresh()->status);
    }

    #[Test]
    public function only_accepted_and_available_drivers_appear_in_the_dispatch_list()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        $accepted = $this->makeDriver('11900000001');
        DriverCompany::create(['driver_id' => $accepted->id, 'company_id' => $company->id, 'status' => Driver::LINK_ACCEPTED, 'delivery_fee' => 10]);

        $pending = $this->makeDriver('11900000002');
        DriverCompany::create(['driver_id' => $pending->id, 'company_id' => $company->id, 'status' => Driver::LINK_PENDING, 'delivery_fee' => 10]);

        $fromOtherCompany = $this->makeDriver('11900000003');
        DriverCompany::create(['driver_id' => $fromOtherCompany->id, 'company_id' => $otherCompany->id, 'status' => Driver::LINK_ACCEPTED, 'delivery_fee' => 10]);

        $client = Client::factory()->create(['company_id' => $company->id]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PROCESSING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 30,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 30,
        ]);

        // Despachar para o motorista aceito funciona normalmente.
        Livewire::test(ManageOrders::class)
            ->callTableAction('ship', $order, data: ['driver_id' => $accepted->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('deliveries', ['order_id' => $order->id, 'driver_id' => $accepted->id]);

        // Motorista com convite pendente e motorista de outra empresa não são
        // opções válidas do select, então a tentativa de despachar pra eles falha.
        $secondOrder = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PROCESSING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 30,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 30,
        ]);

        Livewire::test(ManageOrders::class)
            ->callTableAction('ship', $secondOrder, data: ['driver_id' => $pending->id])
            ->assertHasTableActionErrors(['driver_id']);

        Livewire::test(ManageOrders::class)
            ->callTableAction('ship', $secondOrder, data: ['driver_id' => $fromOtherCompany->id])
            ->assertHasTableActionErrors(['driver_id']);

        $this->assertDatabaseMissing('deliveries', ['order_id' => $secondOrder->id]);
    }
}
