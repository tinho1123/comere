<?php

namespace Tests\Feature\TableSession;

use App\Filament\Admin\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * order_items.cost snapshots the product's cost at the moment of the sale,
 * so a later change to the product's registered cost doesn't retroactively
 * change the profit of a sale that already happened. Covers the two
 * creation paths that look the cost up by product_id instead of having a
 * Product instance already in hand (table session close, admin "novo
 * pedido" form) — the other three (marketplace checkout/reorder, client
 * API) already have $product in scope and are covered elsewhere.
 */
class OrderItemCostSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::factory()->create();
    }

    private function product(Company $company, float $cost): Product
    {
        $category = ProductsCategories::forceCreate(['name' => 'Categoria de teste']);

        return Product::forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Produto de teste',
            'description' => 'Produto de teste',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'cost' => $cost,
            'quantity' => 100,
            'active' => true,
        ]);
    }

    #[Test]
    public function closing_a_table_session_snapshots_the_products_cost_on_the_order_item()
    {
        $company = $this->company();
        $product = $this->product($company, cost: 8);

        $table = TableModel::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'name' => 'Mesa 1',
            'seats' => 4,
            'is_active' => true,
        ]);

        $client = Client::factory()->create();

        $session = TableSession::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'table_id' => $table->id,
            'client_id' => $client->id,
            'guest_name' => 'Cliente',
            'status' => 'open',
            'opened_at' => now(),
            'total_amount' => 0,
        ]);

        TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount,
        ]);

        $session->close('cash');

        $order = Order::where('company_id', $company->id)->firstOrFail();

        $this->assertEquals(8, (float) $order->items->first()->cost);
    }

    #[Test]
    public function creating_an_order_in_the_admin_panel_snapshots_the_products_cost_on_the_order_item()
    {
        $company = $this->company();
        $product = $this->product($company, cost: 8);

        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        $client = Client::factory()->create();
        $client->companies()->attach($company->id);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'client_id' => $client->id,
                'channel' => Order::CHANNEL_PRESENTIAL,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => 1,
                        'unit_price' => $product->amount,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('company_id', $company->id)->firstOrFail();

        $this->assertEquals(8, (float) $order->items->first()->cost);
    }
}
