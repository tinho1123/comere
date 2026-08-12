<?php

namespace Tests\Feature\Drivers;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'Motoboy Teste',
            'phone' => '11912345678',
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('senha123'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function registration_page_is_accessible_from_desktop()
    {
        $response = $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get(route('motoboy.register.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Driver/Register'));
    }

    #[Test]
    public function login_page_shows_the_mobile_only_notice_on_desktop()
    {
        $response = $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->get(route('motoboy.login.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Driver/DesktopBlocked'));
    }

    #[Test]
    public function login_page_works_normally_on_mobile()
    {
        $response = $this->withHeaders(['User-Agent' => self::MOBILE_UA])->get(route('motoboy.login.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Driver/Login'));
    }

    #[Test]
    public function desktop_cannot_log_in_even_with_correct_credentials()
    {
        $this->makeDriver();

        $response = $this->withHeaders(['User-Agent' => self::DESKTOP_UA])->post(route('motoboy.login'), [
            'phone' => '11912345678',
            'password' => 'senha123',
        ]);

        $response->assertInertia(fn ($page) => $page->component('Driver/DesktopBlocked'));
        $this->assertGuest('driver');
    }

    #[Test]
    public function dashboard_shows_the_mobile_only_notice_on_desktop_even_when_logged_in()
    {
        $driver = $this->makeDriver();

        $response = $this->withHeaders(['User-Agent' => self::DESKTOP_UA])
            ->actingAs($driver, 'driver')
            ->get(route('motoboy.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Driver/DesktopBlocked'));
    }

    #[Test]
    public function dashboard_works_normally_on_mobile_when_logged_in()
    {
        $driver = $this->makeDriver();

        $response = $this->withHeaders(['User-Agent' => self::MOBILE_UA])
            ->actingAs($driver, 'driver')
            ->get(route('motoboy.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Driver/Dashboard'));
    }
}
