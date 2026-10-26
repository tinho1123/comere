<?php

namespace Tests\Unit\StockLoss;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\StockLoss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockLossStockTest extends TestCase
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

    #[Test]
    public function registering_a_loss_deducts_stock()
    {
        StockLoss::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => 'quebrado',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 15,
        ]);
    }

    #[Test]
    public function deleting_a_loss_restores_stock()
    {
        $loss = StockLoss::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => 'quebrado',
        ]);

        $loss->delete();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'quantity' => 20,
        ]);
    }
}
