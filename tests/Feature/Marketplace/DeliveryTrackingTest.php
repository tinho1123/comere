<?php

namespace Tests\Feature\Marketplace;

use App\Models\Client;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCompany;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatchedDelivery(Company $company, Client $client): Delivery
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_SHIPPED,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 50,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 50,
            'delivery_latitude' => -23.55,
            'delivery_longitude' => -46.63,
        ]);

        $driver = Driver::create([
            'name' => 'Motoboy Teste',
            'phone' => '119'.random_int(10000000, 99999999),
            'vehicle_type' => Driver::VEHICLE_MOTOBOY,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        DriverCompany::create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'status' => Driver::LINK_ACCEPTED,
            'delivery_fee' => 10,
            'responded_at' => now(),
        ]);

        return Delivery::create([
            'company_id' => $company->id,
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_fee' => 10,
            'is_paid' => false,
            'dispatched_at' => now(),
        ]);
    }

    #[Test]
    public function it_generates_a_tracking_token_automatically()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $delivery = $this->makeDispatchedDelivery($company, $client);

        $this->assertNotEmpty($delivery->tracking_token);
        $this->assertEquals(40, strlen($delivery->tracking_token));
    }

    #[Test]
    public function it_shows_the_public_driver_tracking_page_for_a_valid_token()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->get(route('delivery.tracking.show', $delivery->tracking_token));

        $response->assertOk();
        $response->assertSee($company->name);
        $response->assertSee(strtoupper(substr($delivery->order->uuid, 0, 8)));
    }

    #[Test]
    public function it_returns_404_for_an_invalid_tracking_token()
    {
        $response = $this->get(route('delivery.tracking.show', 'token-invalido'));

        $response->assertNotFound();
    }

    #[Test]
    public function it_updates_the_driver_location_via_the_public_token()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->postJson(route('delivery.tracking.update-location', $delivery->tracking_token), [
            'latitude' => -23.551,
            'longitude' => -46.631,
        ]);

        $response->assertOk();
        $delivery->refresh();
        $this->assertEquals(-23.551, $delivery->current_latitude);
        $this->assertEquals(-46.631, $delivery->current_longitude);
        $this->assertNotNull($delivery->location_updated_at);
    }

    #[Test]
    public function it_rejects_location_updates_once_the_delivery_is_no_longer_dispatched()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->postJson(route('delivery.tracking.update-location', $delivery->tracking_token), [
            'latitude' => -23.551,
            'longitude' => -46.631,
        ]);

        $response->assertStatus(409);
    }

    #[Test]
    public function client_can_track_their_own_order()
    {
        $company = Company::factory()->create(['latitude' => -23.5, 'longitude' => -46.6]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update([
            'current_latitude' => -23.52,
            'current_longitude' => -46.61,
            'location_updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'client')
            ->getJson(route('marketplace.order.track', $delivery->order));

        $response->assertOk();
        $response->assertJson([
            'tracking_available' => true,
            'status' => Delivery::STATUS_DISPATCHED,
            'driver_name' => 'Motoboy Teste',
        ]);
        $response->assertJsonPath('driver_position.latitude', -23.52);
    }

    #[Test]
    public function client_cannot_track_someone_elses_order()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $otherClient = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->actingAs($otherClient, 'client')
            ->getJson(route('marketplace.order.track', $delivery->order));

        $response->assertForbidden();
    }

    #[Test]
    public function it_confirms_pickup_with_the_correct_store_code()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->postJson(route('delivery.tracking.confirm-pickup', $delivery->tracking_token), [
            'code' => $delivery->pickup_code,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertNotNull($delivery->fresh()->picked_up_at);
    }

    #[Test]
    public function it_rejects_pickup_confirmation_with_the_wrong_code()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->postJson(route('delivery.tracking.confirm-pickup', $delivery->tracking_token), [
            'code' => $delivery->pickup_code === '0000' ? '1111' : '0000',
        ]);

        $response->assertStatus(422);
        $this->assertNull($delivery->fresh()->picked_up_at);
    }

    #[Test]
    public function it_confirms_payment_collection_via_the_public_token()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update(['picked_up_at' => now()]);

        $response = $this->postJson(route('delivery.tracking.confirm-payment', $delivery->tracking_token), [
            'code' => $delivery->payment_confirmation_code,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $delivery->refresh();
        $this->assertTrue($delivery->payment_collected);
        $this->assertNotNull($delivery->payment_collected_at);
    }

    #[Test]
    public function it_rejects_payment_confirmation_before_pickup_is_confirmed()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->postJson(route('delivery.tracking.confirm-payment', $delivery->tracking_token), [
            'code' => $delivery->payment_confirmation_code,
        ]);

        $response->assertStatus(409);
        $this->assertFalse($delivery->fresh()->payment_collected);
    }

    #[Test]
    public function it_rejects_payment_confirmation_with_the_wrong_code()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update(['picked_up_at' => now()]);

        $response = $this->postJson(route('delivery.tracking.confirm-payment', $delivery->tracking_token), [
            'code' => $delivery->payment_confirmation_code === '9999' ? '1111' : '9999',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($delivery->fresh()->payment_collected);
    }

    #[Test]
    public function it_rejects_payment_confirmation_once_the_delivery_is_no_longer_dispatched()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->postJson(route('delivery.tracking.confirm-payment', $delivery->tracking_token), [
            'code' => $delivery->payment_confirmation_code,
        ]);

        $response->assertStatus(409);
        $this->assertFalse($delivery->fresh()->payment_collected);
    }

    #[Test]
    public function it_returns_404_for_payment_confirmation_with_an_invalid_token()
    {
        $response = $this->postJson(route('delivery.tracking.confirm-payment', 'token-invalido'), [
            'code' => '0000',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_completes_the_delivery_and_syncs_the_order_status()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->update(['picked_up_at' => now()]);

        $response = $this->postJson(route('delivery.tracking.confirm-delivery', $delivery->tracking_token));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $delivery->refresh();
        $this->assertEquals(Delivery::STATUS_DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertEquals(Order::STATUS_DELIVERED, $delivery->order->fresh()->status);
    }

    #[Test]
    public function it_blocks_completion_of_cash_on_delivery_orders_until_payment_is_collected()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);
        $delivery->order()->update(['payment_method' => 'cash']);
        $delivery->update(['picked_up_at' => now()]);

        $response = $this->postJson(route('delivery.tracking.confirm-delivery', $delivery->tracking_token));

        $response->assertStatus(409);
        $this->assertEquals(Delivery::STATUS_DISPATCHED, $delivery->fresh()->status);
    }

    #[Test]
    public function it_reports_a_delivery_problem()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $response = $this->postJson(route('delivery.tracking.report-problem', $delivery->tracking_token), [
            'reason' => 'customer_unavailable',
        ]);

        $response->assertOk();
        $delivery->refresh();
        $this->assertEquals(Delivery::STATUS_FAILED, $delivery->status);
        $this->assertEquals('customer_unavailable', $delivery->failure_reason);
    }

    #[Test]
    public function it_submits_feedback_only_after_the_delivery_is_completed()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $delivery = $this->makeDispatchedDelivery($company, $client);

        $blocked = $this->postJson(route('delivery.tracking.feedback', $delivery->tracking_token), [
            'rating' => 'good',
        ]);
        $blocked->assertStatus(409);

        $delivery->update(['status' => Delivery::STATUS_DELIVERED, 'delivered_at' => now()]);

        $response = $this->postJson(route('delivery.tracking.feedback', $delivery->tracking_token), [
            'rating' => 'good',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('delivery_feedbacks', [
            'delivery_id' => $delivery->id,
            'driver_id' => $delivery->driver_id,
            'rating' => 'good',
        ]);
    }

    #[Test]
    public function tracking_is_unavailable_when_order_has_no_delivery_yet()
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 10,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 10,
        ]);

        $response = $this->actingAs($client, 'client')
            ->getJson(route('marketplace.order.track', $order));

        $response->assertOk();
        $response->assertJson(['tracking_available' => false]);
    }
}
