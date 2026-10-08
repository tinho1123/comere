<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClientResource;
use App\Filament\Admin\Resources\CompanyHourResource;
use App\Filament\Admin\Resources\CouponResource;
use App\Filament\Admin\Resources\DeliveryFeeRangeResource;
use App\Filament\Admin\Resources\DeliveryResource;
use App\Filament\Admin\Resources\DriverResource;
use App\Filament\Admin\Resources\FavoredTransactionResource;
use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\ProductResource;
use App\Filament\Admin\Resources\ProductSubcategoryResource;
use App\Filament\Admin\Resources\SaleItemResource;
use App\Filament\Admin\Resources\TableResource;
use App\Filament\Admin\Resources\TableSessionResource;
use App\Filament\Admin\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test: every Admin resource's list/manage page must render without
 * blowing up. Added after a Filament v3 class residue in DeliveryResource's
 * getTabs() (see PR #27) caused a production TypeError that no test caught,
 * because nothing exercised that page. This guards every menu, not just
 * Deliveries, against the same class of "resource page is actually broken"
 * regression (e.g. a non-nullable $record type-hint in a column-level
 * ->visible() closure, which Filament evaluates without a record when the
 * table has zero rows).
 */
class AdminResourceSmokeTest extends TestCase
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

    public static function resources(): array
    {
        return [
            'clients' => [ClientResource::class],
            'company hours' => [CompanyHourResource::class],
            'coupons' => [CouponResource::class],
            'delivery fee ranges' => [DeliveryFeeRangeResource::class],
            'deliveries' => [DeliveryResource::class],
            'drivers' => [DriverResource::class],
            'favored transactions' => [FavoredTransactionResource::class],
            'orders' => [OrderResource::class],
            'products' => [ProductResource::class],
            'product subcategories' => [ProductSubcategoryResource::class],
            'sale items' => [SaleItemResource::class],
            'tables' => [TableResource::class],
            'table sessions' => [TableSessionResource::class],
            'users' => [UserResource::class],
        ];
    }

    #[Test]
    #[DataProvider('resources')]
    public function it_can_render_resource_list_page(string $resourceClass)
    {
        $response = $this->get($resourceClass::getUrl('index'));

        $response->assertOk();
    }
}
