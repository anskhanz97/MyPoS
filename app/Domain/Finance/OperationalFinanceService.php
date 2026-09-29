<?php

namespace App\Domain\Finance;

use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\PostCashMovement;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Merchant;
use App\Models\SaleTender;
use App\Models\SettlementBatch;
use App\Models\SettlementLine;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OperationalFinanceService
{
    public function __construct(private readonly PostCashMovement $cash) {}

    /** @return array{expense:Expense,replayed:bool} */
    public function expense(Merchant $m, Store $s, ExpenseCategory $c, User $u, string $key, string $date, string $payee, string $amount, string $source, ?string $reference, string $reason, ?CashSession $session): array
    {
        abort_unless($s->merchant_id === $m->id && $c->merchant_id === $m->id, 404);
        $money = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);
        abort_unless($money->isPositive(), 422);
        abort_unless(in_array($source, ['cash', 'external_recorded'], true), 422);
        abort_unless(($source === 'cash') === ($session instanceof CashSession), 422, 'Cash expenses require a cash session.');
        $hash = hash('sha256', json_encode([$s->public_id, $c->public_id, $date, $payee, (string) $money, $source, $reference, $reason, $session?->public_id], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($m, $s, $c, $u, $key, $date, $payee, $money, $source, $reference, $reason, $session, $hash): array {
            $existing = Expense::query()->where('merchant_id', $m->id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing instanceof Expense) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Expense identity reused with different data.');

                return ['expense' => $existing, 'replayed' => true];
            }$e = Expense::query()->create(['merchant_id' => $m->id, 'store_id' => $s->id, 'expense_category_id' => $c->id, 'cash_session_id' => $session?->id, 'recorded_by_user_id' => $u->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'state' => 'posted', 'expense_date' => $date, 'payee' => $payee, 'amount' => $money, 'payment_source' => $source, 'reference' => $reference, 'reason' => $reason]);
            if ($session instanceof CashSession) {
                abort_unless($session->merchant_id === $m->id && $session->store_id === $s->id, 404);
                $this->cash->handle($session, $u, $key, CashMovementKind::Expense, (string) $money, 'Expense: '.$reason, CarbonImmutable::parse($date)->endOfDay()->min(now())->toAtomString(), 'expense', $e->public_id, true);
            }

            return ['expense' => $e, 'replayed' => false];
        }, attempts: 3);
    }

    public function correct(Expense $original, User $u, string $key, string $reason): Expense
    {
        return DB::transaction(function () use ($original, $u, $key, $reason): Expense {
            $locked = Expense::query()->lockForUpdate()->findOrFail($original->id);
            abort_unless($locked->state === 'posted' && $locked->correction_of_id === null, 409);
            abort_if(Expense::query()->where('correction_of_id', $locked->id)->exists(), 409, 'Expense already corrected.');
            $hash = hash('sha256', $locked->public_id.$reason);
            $e = Expense::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'expense_category_id' => $locked->expense_category_id, 'cash_session_id' => $locked->cash_session_id, 'correction_of_id' => $locked->id, 'recorded_by_user_id' => $u->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'state' => 'correction', 'expense_date' => now()->toDateString(), 'payee' => $locked->payee, 'amount' => BigDecimal::of((string) $locked->amount)->negated(), 'payment_source' => $locked->payment_source, 'reference' => $locked->reference, 'reason' => $reason]);
            if ($locked->cash_session_id !== null) {
                $session = CashSession::query()->findOrFail($locked->cash_session_id);
                $this->cash->handle($session, $u, $key, CashMovementKind::Correction, (string) $locked->amount, 'Expense correction: '.$reason, now()->toAtomString(), 'expense_correction', $e->public_id, true);
            }$locked->forceFill(['state' => 'corrected'])->save();

            return $e;
        }, attempts: 3);
    }

    /** @param list<array{tender_id?:string,external_reference:string,settled_amount:string,fee_amount:string,exception_note?:string}> $lines */
    public function settlement(Merchant $m, Store $s, User $u, string $provider, string $reference, string $start, string $end, array $lines): SettlementBatch
    {
        return DB::transaction(function () use ($m, $s, $u, $provider, $reference, $start, $end, $lines): SettlementBatch {
            $batch = SettlementBatch::query()->create(['merchant_id' => $m->id, 'store_id' => $s->id, 'provider' => $provider, 'statement_reference' => $reference, 'period_start' => $start, 'period_end' => $end, 'provenance' => 'user_entered', 'state' => 'open', 'recorded_by_user_id' => $u->id]);
            $hasException = false;
            foreach ($lines as $line) {
                $tender = isset($line['tender_id']) ? (new SaleTender)->resolveRouteBinding($line['tender_id']) : null;
                abort_unless($tender === null || ($tender instanceof SaleTender && $tender->merchant_id === $m->id && $tender->store_id === $s->id), 404);
                $expected = BigDecimal::of($tender instanceof SaleTender ? (string) $tender->amount : '0')->toScale(2);
                $settled = BigDecimal::of($line['settled_amount'])->toScale(2);
                $fee = BigDecimal::of($line['fee_amount'])->toScale(2);
                $difference = $expected->minus($settled)->minus($fee)->toScale(2);
                $state = ! $tender instanceof SaleTender ? 'unmatched' : ($difference->isZero() ? 'matched' : 'mismatched');
                $hasException = $hasException || $state !== 'matched';
                SettlementLine::query()->create(['merchant_id' => $m->id, 'settlement_batch_id' => $batch->id, 'sale_tender_id' => $tender?->id, 'external_reference' => $line['external_reference'], 'expected_amount' => $expected, 'settled_amount' => $settled, 'fee_amount' => $fee, 'difference_amount' => $difference, 'state' => $state, 'exception_note' => $line['exception_note'] ?? null, 'created_at' => now()]);
            }$batch->forceFill(['state' => $hasException ? 'exceptions' : 'reconciled'])->save();

            return $batch->load('lines');
        }, attempts: 3);
    }
}
