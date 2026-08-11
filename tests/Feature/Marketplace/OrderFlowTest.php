<?php

namespace Tests\Feature\Marketplace;

use App\Models\Client;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductsCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(Company $company, float $amount = 10, bool $active = true): Product
    {
        $category = ProductsCategories::firstOrCreate(['name' => 'Categoria Teste'], ['active' => true]);

        $product = new Product([
            'name' => 'Produto Teste',
            'description' => 'Descrição de teste',
            'amount' => $amount,
            'discounts' => 0,
            'total_amount' => $amount,
            'quantity' => 100,
            'is_marketplace' => true,
            'active' => $active,
            'category_id' => $category->id,
        ]);
        $product->company_id = $company->id;
        $product->save();

        return $product;
    }

    private function makeClient(Company $company): Client
    {
        return Client::factory()->create(['company_id' => $company->id]);
    }

    #[Test]
    public function it_applies_a_valid_coupon_to_order_total()
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $product = $this->makeProduct($company, 100);

        $coupon = Coupon::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'code' => 'DESCONTO10',
            'discount_type' => Coupon::TYPE_PERCENT,
            'discount_value' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
                'coupon_code' => 'desconto10',
            ]);

        $response->assertRedirect(route('marketplace.orders'));

        $order = Order::where('company_id', $company->id)->firstOrFail();
        $this->assertEquals(200, $order->subtotal);
        $this->assertEquals(20, $order->discount_amount);
        $this->assertEquals(180, $order->total_amount);
        $this->assertEquals($coupon->id, $order->coupon_id);
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    #[Test]
    public function it_ignores_invalid_coupon_and_creates_order_without_discount()
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $product = $this->makeProduct($company, 50);

        $response = $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'coupon_code' => 'NAOEXISTE',
            ]);

        $response->assertRedirect(route('marketplace.orders'));

        $order = Order::where('company_id', $company->id)->firstOrFail();
        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(50, $order->total_amount);
        $this->assertNull($order->coupon_id);
    }

    #[Test]
    public function it_does_not_apply_a_coupon_from_a_different_company()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = $this->makeClient($company);
        $product = $this->makeProduct($company, 50);

        Coupon::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'code' => 'FORADALOJA',
            'discount_type' => Coupon::TYPE_PERCENT,
            'discount_value' => 50,
            'active' => true,
        ]);

        $response = $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'coupon_code' => 'FORADALOJA',
            ]);

        $response->assertRedirect(route('marketplace.orders'));

        $order = Order::where('company_id', $company->id)->firstOrFail();
        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(50, $order->total_amount);
    }

    #[Test]
    public function it_sets_estimated_ready_at_based_on_company_avg_preparation_minutes()
    {
        $company = Company::factory()->create(['avg_preparation_minutes' => 45]);
        $client = $this->makeClient($company);
        $product = $this->makeProduct($company, 20);

        $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $order = Order::where('company_id', $company->id)->firstOrFail();
        $this->assertNotNull($order->estimated_ready_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(45)->timestamp,
            $order->estimated_ready_at->timestamp,
            5
        );
    }

    #[Test]
    public function it_recreates_order_with_available_items_on_reorder()
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $activeProduct = $this->makeProduct($company, 30, active: true);
        $discontinuedProduct = $this->makeProduct($company, 15, active: true);

        $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $activeProduct->id, 'quantity' => 1],
                    ['product_id' => $discontinuedProduct->id, 'quantity' => 1],
                ],
            ]);

        $originalOrder = Order::where('company_id', $company->id)->firstOrFail();

        $discontinuedProduct->update(['active' => false]);

        $response = $this->actingAs($client, 'client')
            ->post(route('marketplace.order.reorder', [$company, $originalOrder]));

        $response->assertRedirect(route('marketplace.orders'));

        $newOrder = Order::where('company_id', $company->id)
            ->where('id', '!=', $originalOrder->id)
            ->firstOrFail();

        $this->assertCount(1, $newOrder->items);
        $this->assertEquals($activeProduct->id, $newOrder->items->first()->product_id);
        $this->assertEquals(30, $newOrder->total_amount);
    }

    #[Test]
    public function it_prevents_reordering_someone_elses_order()
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $otherClient = $this->makeClient($company);
        $product = $this->makeProduct($company, 10);

        $this->actingAs($client, 'client')
            ->post(route('marketplace.order.store', $company), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $order = Order::where('client_id', $client->id)->firstOrFail();

        $response = $this->actingAs($otherClient, 'client')
            ->post(route('marketplace.order.reorder', [$company, $order]));

        $response->assertForbidden();
    }
}
