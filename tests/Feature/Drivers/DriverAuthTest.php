<?php

namespace Tests\Feature\Drivers;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsAsMobileDevice;
use Tests\TestCase;

class DriverAuthTest extends TestCase
{
    use InteractsAsMobileDevice;
    use RefreshDatabase;

    #[Test]
    public function a_driver_can_self_register_and_is_logged_in()
    {
        $response = $this->post(route('motoboy.register'), [
            'name' => 'João Motoboy',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ]);

        $response->assertRedirect(route('motoboy.dashboard'));
        $this->assertDatabaseHas('drivers', ['phone' => '11912345678', 'name' => 'João Motoboy']);
        $this->assertAuthenticated('driver');
    }

    #[Test]
    public function registration_fails_with_a_duplicate_phone()
    {
        Driver::create([
            'name' => 'Existente',
            'phone' => '11999999999',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->post(route('motoboy.register'), [
            'name' => 'Outro',
            'phone' => '11999999999',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest('driver');
    }

    #[Test]
    public function a_registered_driver_can_login()
    {
        Driver::create([
            'name' => 'João Motoboy',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('senha123'),
            'is_active' => true,
        ]);

        $response = $this->withMobileUserAgent()->post(route('motoboy.login'), [
            'phone' => '11912345678',
            'password' => 'senha123',
        ]);

        $response->assertRedirect(route('motoboy.dashboard'));
        $this->assertAuthenticated('driver');
    }

    #[Test]
    public function login_fails_with_wrong_password()
    {
        Driver::create([
            'name' => 'João Motoboy',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('senha123'),
            'is_active' => true,
        ]);

        $response = $this->withMobileUserAgent()->post(route('motoboy.login'), [
            'phone' => '11912345678',
            'password' => 'senhaerrada',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest('driver');
    }

    #[Test]
    public function guests_cannot_access_the_dashboard()
    {
        $response = $this->get(route('motoboy.dashboard'));

        $response->assertRedirect(route('motoboy.login.show'));
    }

    #[Test]
    public function the_dashboard_renders_a_working_accept_link_for_pending_invites()
    {
        $driver = Driver::create([
            'name' => 'João Motoboy',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('senha123'),
            'is_active' => true,
        ]);
        $company = Company::factory()->create(['name' => 'Loja Teste']);
        $link = DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_PENDING,
            'delivery_fee' => 12,
        ]);

        // Regressão: withPivot() precisa incluir "id", senão a página monta a
        // URL de aceite/recusa com o parâmetro do vínculo ausente.
        $response = $this->withMobileUserAgent()->actingAs($driver, 'driver')->get(route('motoboy.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('pendingInvites.0.pivot_id', $link->id));
    }
}
