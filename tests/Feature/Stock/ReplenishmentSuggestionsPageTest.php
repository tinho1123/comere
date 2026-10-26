<?php

namespace Tests\Feature\Stock;

use App\Filament\Admin\Pages\ReplenishmentSuggestions;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
