<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the Product create/edit forms against a Filament v3 namespace
 * residue regression: Forms\Get / Forms\Set don't exist in Filament 4
 * (the real classes are Filament\Schemas\Components\Utilities\Get/Set),
 * so any ->visible()/->afterStateUpdated() closure typed against them
 * throws a TypeError as soon as Filament evaluates it while rendering
 * the form. AdminResourceSmokeTest only renders the Product list page,
 * which never exercises these closures.
 */
class ProductResourceFormTest extends TestCase
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
    public function it_can_render_the_create_product_page()
    {
        $response = $this->get(ProductResource::getUrl('create'));

        $response->assertOk();
    }

    #[Test]
    public function it_can_render_the_edit_product_page()
    {
        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $product = new Product([
            'name' => 'Produto Teste',
            'description' => 'Teste',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'cost' => 8,
            'quantity' => 1,
            'category_id' => $category->id,
            'is_for_favored' => true,
            'favored_price' => 22,
        ]);
        $product->company_id = $this->company->id;
        $product->save();

        $response = $this->get(ProductResource::getUrl('edit', ['record' => $product]));

        $response->assertOk();
    }
}
