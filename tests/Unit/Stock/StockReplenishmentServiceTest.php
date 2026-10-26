<?php

namespace Tests\Unit\Stock;

use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Supplier;
use App\Services\StockReplenishmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockReplenishmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected ProductsCategories $category;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 12:00:00');

        $this->company = Company::factory()->create();
        $this->category = ProductsCategories::create(['name' => 'Categoria Teste']);
        $this->client = Client::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeProduct(array $overrides = []): Product
    {
        $product = Product::make(array_merge([
            'name' => 'Brahma Duplo Malte 600ml',
            'description' => 'Cerveja',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 2,
            'purchase_unit_size' => 5,
            'active' => true,
            'category_id' => $this->category->id,
        ], $overrides));

        $product->company_id = $this->company->id;
        $product->save();

        // created_at is auto-managed on create; backdate it directly so the
        // "insufficient history" check sees a product that has existed a while.
        if (isset($overrides['created_at'])) {
            DB::table('products')->where('id', $product->id)->update(['created_at' => $overrides['created_at']]);
            $product->refresh();
        }

        return $product;
    }

    private function sellUnits(Product $product, int $quantity, \DateTimeInterface $at): void
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status' => Order::STATUS_DELIVERED,
            'channel' => 'presential',
            'subtotal' => 0,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 0,
        ]);

        $item = OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * $quantity,
        ]);

        DB::table('order_items')->where('id', $item->id)->update(['created_at' => $at]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => $at]);
    }

    #[Test]
    public function product_with_little_history_is_flagged_instead_of_guessed()
    {
        $product = $this->makeProduct(['created_at' => now()->subDays(5)]);

        $result = app(StockReplenishmentService::class)->suggestionForProduct($product);

        $this->assertFalse($result['has_enough_history']);
        $this->assertSame('Histórico insuficiente', $result['label']);
        $this->assertNull($result['suggested_quantity']);
    }

    #[Test]
    public function without_a_next_visit_date_it_asks_to_set_one_instead_of_guessing()
    {
        $product = $this->makeProduct(['created_at' => now()->subDays(60)]);
        $this->sellUnits($product, 10, now()->subDays(5));

        $result = app(StockReplenishmentService::class)->suggestionForProduct($product);

        $this->assertTrue($result['has_enough_history']);
        $this->assertNull($result['days_to_cover']);
        $this->assertSame('Defina a próxima visita do fornecedor', $result['label']);
    }

    #[Test]
    public function suggested_quantity_covers_sales_until_delivery_rounded_to_the_purchase_unit()
    {
        $product = $this->makeProduct(['created_at' => now()->subDays(60), 'quantity' => 2, 'purchase_unit_size' => 5]);

        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 1,
            'next_visit_at' => now()->addDays(3),
        ]);
        $product->update(['supplier_id' => $supplier->id]);

        // 30 units/day for the last 10 days -> 300 sold, well inside the 30-day window.
        for ($i = 1; $i <= 10; $i++) {
            $this->sellUnits($product, 30, now()->subDays($i));
        }

        $result = app(StockReplenishmentService::class)->suggestionForProduct($product);

        // days_to_cover = days until next visit (3) + lead time (1) = 4
        $this->assertSame(4, $result['days_to_cover']);
        $this->assertNotNull($result['suggested_quantity']);
        $this->assertGreaterThan(0, $result['suggested_quantity']);
        // rounded up to a multiple of the purchase unit size (5)
        $this->assertSame(0, $result['suggested_quantity'] % 5);
    }

    #[Test]
    public function no_suggestion_when_current_stock_already_covers_demand()
    {
        $product = $this->makeProduct(['created_at' => now()->subDays(60), 'quantity' => 1000, 'purchase_unit_size' => 1]);

        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 1,
            'next_visit_at' => now()->addDays(3),
        ]);
        $product->update(['supplier_id' => $supplier->id]);

        $this->sellUnits($product, 5, now()->subDays(2));

        $result = app(StockReplenishmentService::class)->suggestionForProduct($product);

        $this->assertSame(0, $result['suggested_quantity']);
        $this->assertSame('OK, não precisa', $result['label']);
    }
}
