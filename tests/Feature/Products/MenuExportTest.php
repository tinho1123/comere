<?php

namespace Tests\Feature\Products;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCompanyAdmin(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function it_exports_the_menu_with_active_products_grouped_by_category()
    {
        $company = Company::factory()->create(['name' => 'Lanchonete do Zé']);
        $this->actingAsCompanyAdmin($company);

        $category = ProductsCategories::create(['name' => 'Lanches', 'active' => true]);

        $company->products()->create([
            'category_id' => $category->id,
            'name' => 'X-Burger',
            'description' => 'Pão, carne e queijo',
            'amount' => 20,
            'discounts' => 0,
            'total_amount' => 20,
            'quantity' => 1,
            'active' => true,
        ]);

        $inactive = $company->products()->create([
            'category_id' => $category->id,
            'name' => 'Produto Descontinuado',
            'description' => 'Fora de linha',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 1,
            'active' => false,
        ]);

        $response = $this->get(route('admin.products.menu', $company->uuid));

        $response->assertOk();
        $response->assertSee('Lanchonete do Zé');
        $response->assertSee('Lanches');
        $response->assertSee('X-Burger');
        $response->assertDontSee($inactive->name);
    }

    #[Test]
    public function it_forbids_exporting_the_menu_of_a_company_the_user_does_not_belong_to()
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->actingAsCompanyAdmin($company);

        $response = $this->get(route('admin.products.menu', $otherCompany->uuid));

        $response->assertForbidden();
    }
}
