<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource\Pages\EditProduct;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `Product::$hidden = ['cost']` (added to keep cost out of client-facing
 * serialization) also strips it from `EditRecord::fillForm()`, which fills
 * the form from `$record->attributesToArray()`. Without
 * `EditProduct::mutateFormDataBeforeFill()` re-adding it, the Custo field
 * silently renders blank on every edit and saving wipes the stored cost.
 */
class ProductResourceCostFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function editing_a_product_preserves_its_existing_cost()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $product = new Product([
            'name' => 'Produto Teste',
            'description' => 'Descrição',
            'amount' => 34.90,
            'discounts' => 0,
            'total_amount' => 34.90,
            'quantity' => 1,
            'category_id' => $category->id,
            'cost' => 14.50,
        ]);
        $product->company_id = $company->id;
        $product->save();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['cost' => 14.50])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'cost' => 14.50,
        ]);
    }
}
