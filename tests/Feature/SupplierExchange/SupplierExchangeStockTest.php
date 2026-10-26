<?php

namespace Tests\Feature\SupplierExchange;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\SupplierExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierExchangeStockTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $category = ProductsCategories::create(['name' => 'Categoria Teste']);
        $this->product = Product::make([
            'name' => 'Produto Teste',
            'description' => 'Produto para teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 20,
            'active' => true,
            'category_id' => $category->id,
        ]);
        $this->product->company_id = $this->company->id;
        $this->product->save();
    }

    private function createExchange(int $quantity): SupplierExchange
    {
        $exchange = SupplierExchange::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'reason' => 'Defeito de fabricação',
            'status' => SupplierExchange::STATUS_PENDING,
        ]);

        $this->product->decrement('quantity', $quantity);

        return $exchange;
    }

    #[Test]
    public function creating_an_exchange_deducts_stock()
    {
        $this->createExchange(5);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 15,
        ]);
    }

    #[Test]
    public function marking_exchanged_returns_stock()
    {
        $exchange = $this->createExchange(5);

        $exchange->product()->increment('quantity', $exchange->quantity);
        $exchange->update([
            'status' => SupplierExchange::STATUS_EXCHANGED,
            'resolved_at' => now(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 20,
        ]);
        $this->assertSame(SupplierExchange::STATUS_EXCHANGED, $exchange->fresh()->status);
    }

    #[Test]
    public function marking_refused_does_not_return_stock()
    {
        $exchange = $this->createExchange(5);

        $exchange->update([
            'status' => SupplierExchange::STATUS_REFUSED,
            'resolved_at' => now(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 15,
        ]);
        $this->assertSame(SupplierExchange::STATUS_REFUSED, $exchange->fresh()->status);
    }

    #[Test]
    public function deleting_a_pending_exchange_returns_stock()
    {
        $exchange = $this->createExchange(5);

        $exchange->product()->increment('quantity', $exchange->quantity);
        $exchange->delete();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 20,
        ]);
        $this->assertDatabaseMissing('supplier_exchanges', [
            'id' => $exchange->id,
        ]);
    }
}
