<?php

namespace Tests\Feature\Marketplace;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketplaceLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $password = 'password'): Client
    {
        return Client::factory()->create([
            'password' => Hash::make($password),
        ]);
    }

    #[Test]
    public function it_redirects_back_to_the_store_page_the_client_was_on()
    {
        $client = $this->createClient();

        $response = $this->post('/marketplace/login', [
            'email' => $client->email,
            'password' => 'password',
            'redirect_to' => '/store/some-company-uuid',
        ]);

        $response->assertRedirect('/store/some-company-uuid');
    }

    #[Test]
    public function it_falls_back_to_the_marketplace_home_when_no_redirect_to_is_given()
    {
        $client = $this->createClient();

        $response = $this->post('/marketplace/login', [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('marketplace.index'));
    }

    #[Test]
    public function it_ignores_an_absolute_or_protocol_relative_redirect_to_to_avoid_open_redirects()
    {
        $client = $this->createClient();

        $response = $this->post('/marketplace/login', [
            'email' => $client->email,
            'password' => 'password',
            'redirect_to' => 'https://evil.example.com/phishing',
        ]);

        $response->assertRedirect(route('marketplace.index'));

        $this->post('/marketplace/logout');

        $client2 = $this->createClient();
        $response2 = $this->post('/marketplace/login', [
            'email' => $client2->email,
            'password' => 'password',
            'redirect_to' => '//evil.example.com/phishing',
        ]);

        $response2->assertRedirect(route('marketplace.index'));
    }
}
