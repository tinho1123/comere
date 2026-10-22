<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource;
use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
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
 * The product form's "Custo e Margem" section live-computes a margin %
 * and profit-per-unit preview as the admin types the cost, matching the
 * approved design mockup (https://claude.ai/artifact/Da9vP2UPyqBfe69RqHtBYt).
 */
class ProductResourceMarginPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_computes_the_margin_preview_as_cost_is_entered()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'amount' => 34.90,
                'discounts' => 0,
                'cost' => 14.50,
            ])
            ->assertFormSet([
                'margin_percent_preview' => '58,5%',
                'margin_profit_preview' => '20,40',
            ]);
    }

    #[Test]
    public function it_renders_the_margin_status_as_a_real_badge_not_escaped_markup()
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

        $response = $this->get(ProductResource::getUrl('edit', ['record' => $product]));

        $response->assertOk();
        $response->assertSee('<span class="fi-badge-label">', escape: false);
        $response->assertDontSee('&lt;x-filament::badge', escape: false);
    }

    #[Test]
    public function it_computes_stock_cost_and_projected_profit_from_quantity()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'amount' => 34.90,
                'discounts' => 0,
                'cost' => 14.50,
                'quantity' => 10,
            ])
            ->assertFormSet([
                'stock_cost_preview' => '145,00',
                'stock_profit_preview' => '204,00',
            ]);
    }

    #[Test]
    public function it_shows_no_preview_when_cost_is_blank()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'amount' => 34.90,
                'discounts' => 0,
                'cost' => null,
            ])
            ->assertFormSet([
                'margin_percent_preview' => null,
                'margin_profit_preview' => null,
            ]);
    }
}
