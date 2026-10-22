<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\FinancialSummaryWidget;
use App\Filament\Admin\Widgets\ProductMarginWidget;
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
 * Wellington asked (2026-10-22) for the dashboard to reflect what was
 * actually sold, not every non-cancelled order (an online order can sit in
 * pending/processing/shipped for a while before it's truly a sale). Also
 * covers the order_items.cost snapshot, which keeps profit on past sales
 * correct even after a product's registered cost changes later.
 */
class SalesProfitDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::factory()->create();
        $user->companies()->attach($this->company->id);
        $this->actingAs($user);
        Filament::setTenant($this->company);

        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $this->product = new Product([
            'name' => 'Produto Teste',
            'description' => 'Teste',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'cost' => 8,
            'quantity' => 1,
            'category_id' => $category->id,
        ]);
        $this->product->company_id = $this->company->id;
        $this->product->save();
    }

    private function orderWithItem(string $status, ?float $itemCost): Order
    {
        $client = Client::factory()->create();

        $order = new Order;
        $order->uuid = (string) Str::uuid();
        $order->company_id = $this->company->id;
        $order->client_id = $client->id;
        $order->status = $status;
        $order->channel = Order::CHANNEL_ONLINE;
        $order->subtotal = 20;
        $order->total_amount = 20;
        $order->save();

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 20,
            'cost' => $itemCost,
            'total_amount' => 20,
        ]);

        return $order;
    }

    #[Test]
    public function a_delivered_order_counts_but_a_pending_one_does_not()
    {
        $this->orderWithItem(Order::STATUS_DELIVERED, 8);
        $this->orderWithItem(Order::STATUS_PENDING, 8);

        Livewire::test(FinancialSummaryWidget::class)
            ->assertSeeHtml('20,00') // valor bruto: só o pedido entregue
            ->assertSeeHtml('8,00')  // custo: só o pedido entregue
            ->assertSeeHtml('12,00') // líquido
            ->assertDontSeeHtml('40,00'); // somaria 40 se o pendente contasse
    }

    #[Test]
    public function the_cost_snapshot_on_the_order_item_is_used_even_if_the_products_cost_later_changes()
    {
        // Vendido quando o custo era 8; o custo cadastrado do produto já
        // mudou pra 15 — o lucro da venda antiga não deve mudar com isso.
        $this->orderWithItem(Order::STATUS_DELIVERED, 8);
        $this->product->update(['cost' => 15]);

        Livewire::test(FinancialSummaryWidget::class)
            ->assertSeeHtml('8,00')
            ->assertDontSeeHtml('15,00');
    }

    #[Test]
    public function product_margin_widget_only_counts_delivered_orders()
    {
        $this->orderWithItem(Order::STATUS_DELIVERED, 8);
        $this->orderWithItem(Order::STATUS_SHIPPED, 8);

        Livewire::test(ProductMarginWidget::class)
            ->assertSeeHtml('20,00')
            ->assertDontSeeHtml('40,00');
    }
}
