<?php

namespace Tests\Feature\Orders;

use App\Filament\Admin\Resources\OrderResource\Pages\ManageOrders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViewOrderInfolistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_opens_the_view_modal_without_a_container_initialization_error()
    {
        $company = Company::factory()->create();

        $user = User::factory()->create();
        $user->companies()->attach($company->id);
        $this->actingAs($user);
        Filament::setTenant($company);

        $client = Client::factory()->create(['company_id' => $company->id]);
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => Order::STATUS_PENDING,
            'channel' => Order::CHANNEL_ONLINE,
            'subtotal' => 10,
            'discount_amount' => 0,
            'fee_amount' => 0,
            'total_amount' => 10,
        ]);

        // Regressão: Infolists\Components\View não existe no Filament 4.15
        // (resíduo de migração v3->v4). Sem vínculo com o registro, chamar
        // $getRecord() na view do mapa derrubava o modal "Visualizar" com
        // "Typed property ...Component::$container must not be accessed
        // before initialization" (ver PR que troca para ViewEntry).
        Livewire::test(ManageOrders::class)
            ->mountTableAction(ViewAction::class, $order)
            ->assertOk();
    }
}
