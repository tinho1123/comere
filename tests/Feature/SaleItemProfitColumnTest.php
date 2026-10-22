<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SaleItemResource\Pages\ListSaleItems;
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
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wellington asked (2026-10-22) to see the profit per sale, not just an
 * aggregated dashboard widget. The "Transações" list (SaleItemResource)
 * already showed every sold item — this adds Custo/Lucro columns to it.
 */
class SaleItemProfitColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);
    }

    private function orderItem(?float $cost, ?float $productCost = null): OrderItem
    {
        $company = Filament::getTenant();
        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $product = new Product([
            'name' => 'Produto Teste',
            'description' => 'Teste',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'cost' => $productCost,
            'quantity' => 1,
            'category_id' => $category->id,
        ]);
        $product->company_id = $company->id;
        $product->save();

        $client = Client::factory()->create();

        $order = new Order;
        $order->uuid = (string) Str::uuid();
        $order->company_id = $company->id;
        $order->client_id = $client->id;
        $order->status = Order::STATUS_DELIVERED;
        $order->channel = Order::CHANNEL_ONLINE;
        $order->subtotal = 20;
        $order->total_amount = 20;
        $order->save();

        return OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 20,
            'cost' => $cost,
            'total_amount' => 40,
        ]);
    }

    #[Test]
    public function it_shows_cost_and_profit_multiplied_by_quantity_from_the_snapshot()
    {
        $this->orderItem(cost: 8);

        Livewire::test(ListSaleItems::class)
            ->assertSeeHtml('16,00') // custo: 8 x 2
            ->assertSeeHtml('24,00'); // lucro: 40 - 16
    }

    #[Test]
    public function it_falls_back_to_the_products_current_cost_when_the_item_has_no_snapshot()
    {
        $this->orderItem(cost: null, productCost: 8);

        Livewire::test(ListSaleItems::class)
            ->assertSeeHtml('16,00')
            ->assertSeeHtml('24,00');
    }

    #[Test]
    public function it_shows_no_profit_when_there_is_no_cost_at_all()
    {
        $this->orderItem(cost: null, productCost: null);

        Livewire::test(ListSaleItems::class)
            ->assertSeeHtml('Sem custo');
    }
}
