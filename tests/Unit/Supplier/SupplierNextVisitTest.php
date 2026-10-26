<?php

namespace Tests\Unit\Supplier;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierNextVisitTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    #[Test]
    public function weekly_supplier_next_visit_is_one_week_after_last_visit()
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 1,
            'last_visit_at' => '2026-10-20',
        ]);

        $this->assertSame('2026-10-27', $supplier->next_visit_at->format('Y-m-d'));
        $this->assertFalse($supplier->next_visit_overridden);
    }

    #[Test]
    public function monthly_supplier_next_visit_is_one_month_after_last_visit()
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Lokal Cervejaria',
            'visit_frequency' => Supplier::FREQUENCY_MONTHLY,
            'lead_time_days' => 3,
            'last_visit_at' => '2026-10-03',
        ]);

        $this->assertSame('2026-11-03', $supplier->next_visit_at->format('Y-m-d'));
    }

    #[Test]
    public function marking_visited_recalculates_next_visit_and_clears_override()
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Ambev',
            'visit_frequency' => Supplier::FREQUENCY_WEEKLY,
            'lead_time_days' => 1,
            'last_visit_at' => '2026-10-20',
            'next_visit_at' => '2026-11-10',
            'next_visit_overridden' => true,
        ]);

        $supplier->markVisited('2026-10-27');

        $this->assertSame('2026-10-27', $supplier->fresh()->last_visit_at->format('Y-m-d'));
        $this->assertSame('2026-11-03', $supplier->fresh()->next_visit_at->format('Y-m-d'));
        $this->assertFalse($supplier->fresh()->next_visit_overridden);
    }
}
