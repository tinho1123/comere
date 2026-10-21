<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductsCategories;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Product list page's ImageColumn had no ->disk('public') set, so it
 * checked file existence against the default filesystem disk ('local'),
 * never found the file (actually stored on the 'public' disk, same as the
 * form's FileUpload), and silently fell back to the placeholder icon for
 * every product with an image.
 */
class ProductResourceImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_product_list_resolves_the_image_url_from_the_public_disk()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        $category = ProductsCategories::create(['name' => 'Categoria Teste', 'active' => true]);

        $path = UploadedFile::fake()->image('produto.jpg')
            ->storeAs('store/'.$company->uuid.'/products', 'produto.jpg', 'public');

        $product = new Product([
            'name' => 'Produto com imagem',
            'description' => 'Teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'quantity' => 1,
            'category_id' => $category->id,
            'image' => $path,
        ]);
        $product->company_id = $company->id;
        $product->save();

        $response = $this->get(ProductResource::getUrl('index'));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($path), escape: false);
        $response->assertDontSee('data:image/svg+xml', escape: false);
    }
}
