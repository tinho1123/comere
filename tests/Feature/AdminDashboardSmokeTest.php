<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Dashboard;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test for the admin dashboard, in the same spirit as AdminResourceSmokeTest:
 * guards the Custo e Margem widgets (FinancialSummaryWidget, ProductMarginWidget)
 * against breaking when a product has no cost, or when there are zero orders.
 */
class AdminDashboardSmokeTest extends TestCase
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
    public function it_can_render_dashboard_with_no_orders()
    {
        $response = $this->get(Dashboard::getUrl());

        $response->assertOk();
    }

    #[Test]
    public function it_can_render_dashboard_with_mixed_cost_products()
    {
        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $productWithCost = new Product([
            'name' => 'Produto com custo',
            'description' => 'Teste',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'cost' => 8,
            'quantity' => 1,
            'category_id' => $category->id,
        ]);
        $productWithCost->company_id = $this->company->id;
        $productWithCost->save();

        $productWithoutCost = new Product([
            'name' => 'Produto sem custo',
            'description' => 'Teste',
            'amount' => 15,
            'discounts' => 0,
            'total_amount' => 15,
            'cost' => null,
            'quantity' => 1,
            'category_id' => $category->id,
        ]);
        $productWithoutCost->company_id = $this->company->id;
        $productWithoutCost->save();

        $client = Client::factory()->create();

        $order = new Order;
        $order->uuid = (string) Str::uuid();
        $order->company_id = $this->company->id;
        $order->client_id = $client->id;
        $order->status = Order::STATUS_DELIVERED;
        $order->channel = Order::CHANNEL_ONLINE;
        $order->subtotal = 35;
        $order->total_amount = 35;
        $order->save();

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $productWithCost->id,
            'product_name' => $productWithCost->name,
            'quantity' => 1,
            'unit_price' => 20,
            'total_amount' => 20,
        ]);

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $productWithoutCost->id,
            'product_name' => $productWithoutCost->name,
            'quantity' => 1,
            'unit_price' => 15,
            'total_amount' => 15,
        ]);

        $response = $this->get(Dashboard::getUrl());

        $response->assertOk();
    }
}
