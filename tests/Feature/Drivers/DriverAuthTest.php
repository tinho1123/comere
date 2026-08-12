<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverAuthTest extends TestCase
{
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

        $response = $this->post(route('motoboy.login'), [
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

        $response = $this->post(route('motoboy.login'), [
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
}
