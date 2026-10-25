<?php

namespace Tests\Feature\TableSession;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Table as TableModel;
use App\Models\TableSession;
use App\Models\TableSessionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stock is reserved (debited) the moment an item is added to an open table
 * session, not only when the session is closed — so other guests at the
 * table and the marketplace stop seeing it as available right away. See
 * TableSessionItem::booted() and TableSession::close()/closeAsFavored().
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::factory()->create();
    }

    private function product(Company $company, int $quantity): Product
    {
        $category = ProductsCategories::forceCreate(['name' => 'Categoria de teste']);

        return Product::forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Produto de teste',
            'description' => 'Produto de teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => $quantity,
            'active' => true,
        ]);
    }

    private function openSession(Company $company): TableSession
    {
        $table = TableModel::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'name' => 'Mesa 1',
            'is_active' => true,
        ]);

        return TableSession::create([
            'uuid' => (string) str()->uuid(),
            'company_id' => $company->id,
            'table_id' => $table->id,
            'status' => 'open',
            'opened_at' => now(),
            'total_amount' => 0,
        ]);
    }

    #[Test]
    public function adding_an_item_to_an_open_session_reserves_the_stock_immediately()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 10);
        $session = $this->openSession($company);

        TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * 3,
        ]);

        $this->assertEquals(7, $product->fresh()->quantity);
    }

    #[Test]
    public function it_refuses_to_add_an_item_when_stock_is_insufficient()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 2);
        $session = $this->openSession($company);

        $this->expectException(\RuntimeException::class);

        TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * 3,
        ]);

        $this->assertEquals(2, $product->fresh()->quantity);
        $this->assertEquals(0, $session->items()->count());
    }

    #[Test]
    public function closing_the_session_does_not_decrement_stock_a_second_time()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 10);
        $session = $this->openSession($company);

        TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 4,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * 4,
        ]);

        $this->assertEquals(6, $product->fresh()->quantity);

        $session->close('cash');

        $this->assertEquals(6, $product->fresh()->quantity);
    }

    #[Test]
    public function removing_an_item_restores_the_reserved_stock()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 10);
        $session = $this->openSession($company);

        $item = TableSessionItem::create([
            'table_session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 4,
            'unit_price' => $product->amount,
            'total_amount' => $product->amount * 4,
        ]);

        $this->assertEquals(6, $product->fresh()->quantity);

        $item->delete();

        $this->assertEquals(10, $product->fresh()->quantity);
    }

    #[Test]
    public function decrementing_stock_never_goes_below_zero()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 1);

        Product::decrementStockSafely($product->id, 5);

        $this->assertEquals(0, $product->fresh()->quantity);
    }

    #[Test]
    public function insufficient_stock_message_sums_quantity_across_rows_of_the_same_product()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 5);

        // Duas linhas de 3 unidades cada: nenhuma sozinha excede o estoque
        // (5), mas juntas pedem 6 — tem que ser bloqueado.
        $message = Product::findInsufficientStockMessage([
            ['product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->assertNotNull($message);
        $this->assertStringContainsString('Estoque insuficiente', $message);
    }

    #[Test]
    public function insufficient_stock_message_is_null_when_combined_quantity_fits()
    {
        $company = $this->company();
        $product = $this->product($company, quantity: 10);

        $message = Product::findInsufficientStockMessage([
            ['product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->assertNull($message);
    }

    #[Test]
    public function insufficient_stock_message_ignores_a_product_from_another_company()
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $otherCompanysProduct = $this->product($companyB, quantity: 1);

        // Pedido de uma empresa citando o produto de outra: a checagem
        // escopada por company_id não deve devolver nome/estoque de um
        // produto que não pertence a essa empresa.
        $message = Product::findInsufficientStockMessage([
            ['product_id' => $otherCompanysProduct->id, 'quantity' => 5],
        ], $companyA->id);

        $this->assertNull($message);
    }
}
