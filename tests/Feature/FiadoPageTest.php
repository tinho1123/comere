<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\FiadoPage;
use App\Models\Company;
use App\Models\FavoredTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiadoPageTest extends TestCase
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
    public function it_can_render_the_client_list()
    {
        Livewire::test(FiadoPage::class)->assertOk();
    }

    #[Test]
    public function it_can_select_a_client_and_show_the_summary_without_errors()
    {
        FavoredTransaction::create([
            'company_id' => $this->company->id,
            'name' => 'Produto Teste',
            'client_name' => 'Cliente Teste',
            'amount' => 10,
            'discounts' => 0,
            'total_amount' => 10,
            'favored_total' => 10,
            'favored_paid_amount' => 0,
            'quantity' => 1,
            'active' => true,
        ]);

        // Regressão: a tela de detalhe do cliente chamava Stat::toHtml()
        // diretamente (sem um container, igual ao bug do infolist de
        // Pedidos) e usava classes Tailwind que não existem no CSS do
        // admin — ambos derrubavam esta tela com 500 ao clicar em
        // "Ver Fiados".
        Livewire::test(FiadoPage::class)
            ->call('selectClient', 'Cliente Teste')
            ->assertOk()
            ->assertSee('Cliente Teste')
            ->assertSee('R$ 10,00');
    }
}
