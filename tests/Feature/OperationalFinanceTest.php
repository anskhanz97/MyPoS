<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Register;
use App\Models\SettlementLine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class OperationalFinanceTest extends TestCase
{
    use BuildsPricingFixture,LazilyRefreshDatabase;

    public function test_expense_is_retry_safe_links_cash_once_validates_attachment_and_corrects_with_audit_trail(): void
    {
        $f = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $f['merchant']->id, 'store_id' => $f['store']->id]);
        $base = "/api/v1/merchants/{$f['merchant']->public_id}/stores/{$f['store']->public_id}";
        $session = $this->actingAs($f['cashier'])->postJson($base."/registers/{$register->public_id}/cash-sessions", ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '10000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cat = ExpenseCategory::query()->create(['merchant_id' => $f['merchant']->id, 'code' => 'UTIL', 'name' => 'Utilities', 'is_active' => true]);
        $payload = ['category_id' => $cat->public_id, 'expense_date' => now()->toDateString(), 'payee' => 'Electricity vendor', 'amount' => '1200.00', 'payment_source' => 'cash', 'cash_session_id' => $session['id'], 'reference' => 'BILL-1', 'reason' => 'Monthly electricity bill'];
        $key = (string) Str::uuid();
        $expense = $this->actingAs($f['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base.'/expenses', $payload)->assertCreated()->json('data');
        $this->actingAs($f['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base.'/expenses', $payload)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertSame(1, CashMovement::query()->where('kind', 'expense')->count());
        $this->actingAs($f['cashier'])->withHeader('Idempotency-Key', $key)->postJson($base.'/expenses', array_merge($payload, ['amount' => '1300.00']))->assertConflict();
        $this->actingAs($f['manager'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/expenses/'.$expense['public_id'].'/corrections', ['reason' => 'Invoice was entered against wrong period'])->assertCreated();
        $this->assertSame(1, CashMovement::query()->where('kind', 'correction')->count());
        $this->assertSame('corrected', Expense::query()->findOrFail($expense['id'])->state);
        Storage::fake('local');
        $bad = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');
        $this->actingAs($f['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->post($base.'/expenses', array_merge($payload, ['payment_source' => 'external_recorded', 'cash_session_id' => null, 'attachment' => $bad]), ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_user_entered_settlement_preserves_unmatched_and_fee_evidence(): void
    {
        $f = $this->pricingFixture();
        $base = "/api/v1/merchants/{$f['merchant']->public_id}/stores/{$f['store']->public_id}";
        $this->actingAs($f['manager'])->postJson($base.'/settlements', ['provider' => 'Manual card statement', 'statement_reference' => 'STM-SEP-1', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'lines' => [['external_reference' => 'UNKNOWN-1', 'settled_amount' => '980.00', 'fee_amount' => '20.00', 'exception_note' => 'No matching tender reference']]])->assertCreated()->assertJsonPath('data.state', 'exceptions')->assertJsonPath('data.lines.0.state', 'unmatched');
        $line = SettlementLine::query()->firstOrFail();
        $this->assertSame('0.00', $line->expected_amount);
        $this->assertSame('-1000.00', $line->difference_amount);
    }
}
