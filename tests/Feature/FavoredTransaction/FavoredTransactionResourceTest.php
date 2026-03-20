<?php

namespace Tests\Feature\FavoredTransaction;

use App\Filament\Admin\Resources\FavoredTransactionResource\Pages\CreateFavoredTransaction;
use App\Filament\Admin\Resources\FavoredTransactionResource\Pages\EditFavoredTransaction;
use App\Filament\Admin\Resources\FavoredTransactionResource\Pages\ListFavoredTransactions;
use App\Models\Client;
use App\Models\Company;
use App\Models\FavoredTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FavoredTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->client = Client::factory()->create(['company_id' => $this->company->id]);

        // Associate user with company
        $this->user->companies()->attach($this->company->id);
        $this->actingAs($this->user);

        // Set Filament tenant context
        Filament::setTenant($this->company);
    }

    /** @test */
    public function it_can_render_favored_transactions_list_page()
    {
        $response = $this->get("/admin/{$this->company->uuid}/favored-transactions");

        $response->assertOk();
    }

    /** @test */
    public function it_can_render_favored_transactions_create_page()
    {
        $response = $this->get("/admin/{$this->company->uuid}/favored-transactions/create");

        $response->assertOk();
    }

    /** @test */
    public function it_can_render_favored_transactions_edit_page()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->get("/admin/{$this->company->uuid}/favored-transactions/{$transaction->uuid}/edit");

        $response->assertOk();
    }

    /** @test */
    public function it_displays_client_relationship_in_table()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Test Transaction',
            'favored_total' => 100.00,
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->assertTableColumnExists('person_name')
            ->assertTableColumnStateSet('person_name', $this->client->name, $transaction);
    }

    /** @test */
    public function it_displays_remaining_balance_in_table()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'favored_total' => 100.00,
            'favored_paid_amount' => 30.00,
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->assertTableColumnExists('remaining')
            ->assertTableColumnStateSet('remaining', 70.00, $transaction);
    }

    /** @test */
    public function it_can_create_favored_transaction_via_form()
    {
        $product = \App\Models\Product::forceCreate([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'company_id' => $this->company->id,
            'name' => 'Produto Fiado',
            'amount' => 50.00,
            'favored_price' => 45.00,
            'total_amount' => 50.00,
            'is_for_favored' => true,
            'quantity' => 10,
            'active' => true,
        ]);

        Livewire::test(CreateFavoredTransaction::class)
            ->fillForm([
                'is_registered_client' => true,
                'client_id' => $this->client->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'favored_price' => 45.00,
                        'product_name' => $product->name,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('favored_transactions', [
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'name' => $product->name,
            'favored_total' => 90.00,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        Livewire::test(CreateFavoredTransaction::class)
            ->fillForm(['is_registered_client' => true])
            ->call('create')
            ->assertHasFormErrors(['client_id']);
    }

    /** @test */
    public function it_can_edit_favored_transaction_via_form()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Original Name',
            'favored_total' => 100.00,
        ]);

        $editData = [
            'favored_total' => 200.00,
            'favored_paid_amount' => 50.00,
            'quantity' => 3,
        ];

        Livewire::test(EditFavoredTransaction::class, [
            'record' => $transaction->getRouteKey(),
        ])
            ->fillForm($editData)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('favored_transactions', [
            'id' => $transaction->id,
            'favored_total' => 200.00,
            'favored_paid_amount' => 50.00,
        ]);
    }

    /** @test */
    public function it_can_delete_favored_transaction()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->callTableAction('delete', $transaction);

        $this->assertModelMissing($transaction);
    }

    /** @test */
    public function it_filters_transactions_by_company()
    {
        // Create transaction for our company
        $companyTransaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Company Transaction',
        ]);

        // Create transaction for another company
        $otherCompany = Company::factory()->create();
        $otherTransaction = FavoredTransaction::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Transaction',
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->assertCanSeeTableRecords([$companyTransaction])
            ->assertCanNotSeeTableRecords([$otherTransaction]);
    }

    /** @test */
    public function it_shows_money_columns_with_brl_formatting()
    {
        $transaction = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'favored_total' => 1234.56,
            'favored_paid_amount' => 789.10,
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->assertTableColumnFormattedStateSet('favored_total', "R\$\xc2\xa01.234,56", $transaction)
            ->assertTableColumnFormattedStateSet('favored_paid_amount', "R\$\xc2\xa0789,10", $transaction);
    }

    /** @test */
    public function it_searches_by_client_name()
    {
        $client1 = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'João Silva']);
        $client2 = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Maria Souza']);

        $transaction1 = FavoredTransaction::factory()->create(['client_id' => $client1->id, 'company_id' => $this->company->id]);
        $transaction2 = FavoredTransaction::factory()->create(['client_id' => $client2->id, 'company_id' => $this->company->id]);

        Livewire::test(ListFavoredTransactions::class)
            ->searchTable('João')
            ->assertCanSeeTableRecords([$transaction1])
            ->assertCanNotSeeTableRecords([$transaction2]);
    }

    /** @test */
    public function it_searches_by_transaction_name()
    {
        $transaction1 = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Venda Especial',
        ]);

        $transaction2 = FavoredTransaction::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Venda Normal',
        ]);

        Livewire::test(ListFavoredTransactions::class)
            ->searchTable('Especial')
            ->assertCanSeeTableRecords([$transaction1])
            ->assertCanNotSeeTableRecords([$transaction2]);
    }
}
