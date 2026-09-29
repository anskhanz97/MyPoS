<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Finance\OperationalFinanceService;
use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\Merchant;
use App\Models\SettlementBatch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalFinanceController extends Controller
{
    public function categories(Request $r, Merchant $merchant, RoleAuthorizer $a): JsonResponse
    {
        $a->authorize($this->user($r), $merchant, Permission::ExpenseSubmit);

        return response()->json(['data' => ExpenseCategory::query()->where('merchant_id', $merchant->id)->orderBy('name')->get()]);
    }

    public function createCategory(Request $r, Merchant $merchant, RoleAuthorizer $a): JsonResponse
    {
        $a->authorize($this->user($r), $merchant, Permission::ExpenseApprove);
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:40'], 'name' => ['required', 'string', 'max:120']]);

        return response()->json(['data' => ExpenseCategory::query()->create(['merchant_id' => $merchant->id] + $d + ['is_active' => true])], 201);
    }

    public function expenses(Request $r, Merchant $merchant, Store $store, RoleAuthorizer $a): JsonResponse
    {
        $a->authorize($this->user($r), $merchant, Permission::ExpenseSubmit, $store);

        return response()->json(['data' => Expense::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->latest('expense_date')->limit(250)->get()]);
    }

    public function createExpense(Request $r, Merchant $merchant, Store $store, RoleAuthorizer $a, OperationalFinanceService $service): JsonResponse
    {
        $u = $this->user($r);
        $a->authorize($u, $merchant, Permission::ExpenseSubmit, $store);
        $d = $r->validate(['category_id' => ['required', 'string'], 'expense_date' => ['required', 'date'], 'payee' => ['required', 'string', 'max:160'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'payment_source' => ['required', 'in:cash,external_recorded'], 'cash_session_id' => ['nullable', 'string'], 'reference' => ['nullable', 'string', 'max:160'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $cat = (new ExpenseCategory)->resolveRouteBinding($d['category_id']);
        abort_unless($cat instanceof ExpenseCategory, 404);
        $session = null;
        if (isset($d['cash_session_id'])) {
            $session = (new CashSession)->resolveRouteBinding($d['cash_session_id']);
            abort_unless($session instanceof CashSession, 404);
        }$result = $service->expense($merchant, $store, $cat, $u, $this->key($r), $d['expense_date'], $d['payee'], $d['amount'], $d['payment_source'], $d['reference'] ?? null, $d['reason'], $session);
        if ($r->hasFile('attachment') && ! $result['replayed']) {
            $file = $r->file('attachment');
            abort_unless($file !== null && $file->isValid(), 422);
            $path = $file->store('expense-attachments/'.$merchant->public_id, 'local');
            abort_unless(is_string($path), 500);
            ExpenseAttachment::query()->create(['merchant_id' => $merchant->id, 'expense_id' => $result['expense']->id, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now()]);
        }

        return response()->json(['data' => $result['expense'], 'meta' => ['replayed' => $result['replayed']]], $result['replayed'] ? 200 : 201);
    }

    public function correct(Request $r, Merchant $merchant, Store $store, Expense $expense, RoleAuthorizer $a, OperationalFinanceService $service): JsonResponse
    {
        $u = $this->user($r);
        $a->authorize($u, $merchant, Permission::ExpenseApprove, $store);
        abort_unless($expense->merchant_id === $merchant->id && $expense->store_id === $store->id, 404);
        $d = $r->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json(['data' => $service->correct($expense, $u, $this->key($r), $d['reason'])], 201);
    }

    public function download(Request $r, Merchant $merchant, ExpenseAttachment $expenseAttachment, RoleAuthorizer $a): StreamedResponse
    {
        $a->authorize($this->user($r), $merchant, Permission::ExpenseApprove);
        abort_unless($expenseAttachment->merchant_id === $merchant->id, 404);
        abort_unless(Storage::disk($expenseAttachment->disk)->exists($expenseAttachment->path), 404);

        return Storage::disk($expenseAttachment->disk)->download($expenseAttachment->path, $expenseAttachment->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function createSettlement(Request $r, Merchant $merchant, Store $store, RoleAuthorizer $a, OperationalFinanceService $service): JsonResponse
    {
        $u = $this->user($r);
        $a->authorize($u, $merchant, Permission::ExpenseApprove, $store);
        $d = $r->validate(['provider' => ['required', 'string', 'max:120'], 'statement_reference' => ['required', 'string', 'max:160'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'lines' => ['required', 'array', 'min:1', 'max:1000'], 'lines.*.tender_id' => ['nullable', 'string'], 'lines.*.external_reference' => ['required', 'string', 'max:160'], 'lines.*.settled_amount' => ['required', 'decimal:0,2', 'gte:0'], 'lines.*.fee_amount' => ['required', 'decimal:0,2', 'gte:0'], 'lines.*.exception_note' => ['nullable', 'string', 'max:500']]);

        return response()->json(['data' => $service->settlement($merchant, $store, $u, $d['provider'], $d['statement_reference'], $d['period_start'], $d['period_end'], $d['lines'])], 201);
    }

    public function settlements(Request $r, Merchant $merchant, Store $store, RoleAuthorizer $a): JsonResponse
    {
        $a->authorize($this->user($r), $merchant, Permission::ExpenseApprove, $store);

        return response()->json(['data' => SettlementBatch::query()->with('lines')->where('merchant_id', $merchant->id)->where('store_id', $store->id)->latest()->limit(100)->get()]);
    }

    private function user(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }

    private function key(Request $r): string
    {
        $k = $r->header('Idempotency-Key');
        abort_unless(is_string($k) && preg_match('/^[0-9a-f-]{36}$/i', $k) === 1, 422, 'A UUID Idempotency-Key header is required.');

        return $k;
    }
}
