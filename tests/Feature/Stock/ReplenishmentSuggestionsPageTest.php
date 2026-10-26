<?php

namespace Tests\Feature\Stock;

use App\Filament\Admin\Pages\ReplenishmentSuggestions;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test for the "Sugestão de Reposição" page, in the same spirit as
 * AdminResourceSmokeTest: renders it with no products, and with a product
 * linked to a supplier, to catch a broken column closure early.
 */
class ReplenishmentSuggestionsPageTest extends TestCase
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
    public function it_can_render_with_no_products()
    {
        $response = $this->get(ReplenishmentSuggestions::getUrl());

        $response->assertOk();
    }

    #[Test]
    public function it_can_render_with_a_product_linked_to_a_supplier()
    {
        $category = ProductsCategories::create(['name' => 'Categoria Teste']);

        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 1,
            'next_visit_at' => now()->addDays(3),
        ]);

        $product = Product::make([
            'name' => 'Brahma Duplo Malte 600ml',
            'description' => 'Cerveja',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 5,
            'active' => true,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
        ]);
        $product->company_id = $this->company->id;
        $product->save();

        $response = $this->get(ReplenishmentSuggestions::getUrl());

        $response->assertOk();
        $response->assertSee('Brahma Duplo Malte 600ml');
    }

    #[Test]
    public function generating_an_order_from_a_suggestion_creates_a_pending_supplier_order()
    {
        $category = ProductsCategories::create(['name' => 'Categoria Teste']);

        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 2,
            'next_visit_at' => now()->addDays(3),
        ]);

        $product = Product::make([
            'name' => 'Brahma Duplo Malte 600ml',
            'description' => 'Cerveja',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 1,
            'purchase_unit_size' => 1,
            'active' => true,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
        ]);
        $product->company_id = $this->company->id;
        $product->save();
        DB::table('products')->where('id', $product->id)->update(['created_at' => now()->subDays(60)]);

        $client = Client::factory()->create();

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_DELIVERED,
            'channel' => 'presential',
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);
        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 30,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * 30,
        ]);

        Livewire::test(ReplenishmentSuggestions::class)
            ->callTableAction('generate_order', $product, data: ['quantity_ordered' => 20])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('supplier_orders', [
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'quantity_ordered' => 20,
            'status' => SupplierOrder::STATUS_PENDING,
        ]);
    }
}
