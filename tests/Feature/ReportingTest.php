<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_store_and_consolidated_reports_reconcile_without_cross_store_leakage(): void
    {
        $fixture = $this->pricingFixture();
        $category = ExpenseCategory::query()->create([
            'merchant_id' => $fixture['merchant']->id,
            'code' => 'UTIL',
            'name' => 'Utilities',
            'is_active' => true,
        ]);
        $this->expense($fixture['merchant'], $fixture['store']->id, $category->id, '125.50');
        $this->expense($fixture['merchant'], $fixture['otherStore']->id, $category->id, '74.50');

        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/reports/expenses?start=2026-09-01&end=2026-09-30";
        $this->actingAs($fixture['manager'])
            ->getJson($base.'&store_id='.$fixture['store']->public_id)
            ->assertOk()
            ->assertJsonPath('data.scope', 'store')
            ->assertJsonPath('data.data.net_expenses', '125.50')
            ->assertJsonPath('data.data.records', 1);

        $this->actingAs($fixture['owner'])
            ->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.scope', 'merchant')
            ->assertJsonPath('data.data.net_expenses', '200.00')
            ->assertJsonPath('data.data.records', 2)
            ->assertJsonPath('data.freshness.mode', 'live-ledger');

        $this->actingAs($fixture['manager'])->getJson($base)->assertForbidden();
        $this->actingAs($fixture['manager'])
            ->getJson(str_replace('expenses', 'workforce', $base).'&store_id='.$fixture['store']->public_id)
            ->assertForbidden();
        $this->actingAs($fixture['owner'])
            ->getJson("/api/v1/merchants/{$fixture['merchant']->public_id}/reports/sales?start=2025-01-01&end=2026-09-30")
            ->assertUnprocessable();
    }

    public function test_saved_filters_are_private_and_reject_foreign_store_substitution(): void
    {
        $fixture = $this->pricingFixture();
        $otherMerchant = Merchant::factory()->create();
        $foreignStore = Store::factory()->for($otherMerchant)->create();
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/saved-report-filters";
        $payload = [
            'report_key' => 'sales',
            'name' => 'Month end',
            'filters' => [
                'start' => '2026-09-01',
                'end' => '2026-09-30',
                'store_id' => $fixture['store']->public_id,
            ],
        ];

        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertCreated();
        $this->actingAs($fixture['manager'])->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($fixture['cashier'])->getJson($url)->assertOk()->assertJsonCount(0, 'data');

        $payload['filters']['store_id'] = $foreignStore->public_id;
        $this->actingAs($fixture['manager'])->postJson($url, $payload)->assertNotFound();

        $csv = $this->actingAs($fixture['manager'])
            ->get("/api/v1/merchants/{$fixture['merchant']->public_id}/reports/expenses/export.csv?start=2026-09-01&end=2026-09-30&store_id={$fixture['store']->public_id}")
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('net_expenses', $csv);
    }

    private function expense(Merchant $merchant, int $storeId, int $categoryId, string $amount): void
    {
        Expense::query()->create([
            'merchant_id' => $merchant->id,
            'store_id' => $storeId,
            'expense_category_id' => $categoryId,
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', Str::random()),
            'state' => 'recorded',
            'expense_date' => '2026-09-15',
            'payee' => 'Test supplier',
            'amount' => $amount,
            'payment_source' => 'external',
            'reason' => 'Golden reporting fixture',
        ]);
    }
}
