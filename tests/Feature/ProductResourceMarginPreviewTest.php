<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Company;
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
