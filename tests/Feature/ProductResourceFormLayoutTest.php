<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filament 4 wraps a resource form with a default 2-column grid unless the
 * schema explicitly overrides it. ProductResource::form() never called
 * ->columns(1), so its top-level Grid/Section components (quantity/category,
 * "Custo e Margem", "Configurações de Fiado", "Acréscimos...") were split
 * across two columns instead of stacking vertically, squeezing the category
 * select and scattering the sections sideways in production.
 */
class ProductResourceFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_create_product_form_renders_as_a_single_column()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        $response = $this->get(ProductResource::getUrl('create'));
        $response->assertOk();

        preg_match('/schemaKey: \'form\'[^>]*style="([^"]*)"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches, 'Could not find the form schema grid wrapper in the rendered page.');
        $this->assertStringContainsString('--cols-lg: repeat(1, minmax(0, 1fr));', $matches[1]);
    }
}
