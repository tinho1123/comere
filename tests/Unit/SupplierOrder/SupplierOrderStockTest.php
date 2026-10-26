<?php

namespace Tests\Unit\SupplierOrder;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierOrderStockTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $category = ProductsCategories::create(['name' => 'Categoria Teste']);
        $this->supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 2,
        ]);
        $this->product = Product::make([
            'name' => 'Produto Teste',
            'description' => 'Produto para teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 5,
            'active' => true,
            'category_id' => $category->id,
            'supplier_id' => $this->supplier->id,
        ]);
        $this->product->company_id = $this->company->id;
        $this->product->save();
    }

    #[Test]
    public function marking_an_order_received_adds_stock_and_sets_status()
    {
        $order = SupplierOrder::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'product_id' => $this->product->id,
            'quantity_suggested' => 48,
            'quantity_ordered' => 48,
            'requested_at' => now(),
            'expected_delivery_at' => now()->addDays(2),
            'status' => SupplierOrder::STATUS_PENDING,
        ]);

        $order->markReceived();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 53,
        ]);
        $this->assertSame(SupplierOrder::STATUS_RECEIVED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->received_at);
    }
}
