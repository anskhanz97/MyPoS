<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Purchasing\ConfirmSupplierCredit;
use App\Domain\Purchasing\PostSupplierPayment;
use App\Domain\Purchasing\ReverseSupplierPayment;
use App\Domain\Purchasing\SupplierStatement;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmSupplierCreditRequest;
use App\Http\Requests\PostSupplierPaymentRequest;
use App\Http\Requests\ReverseSupplierPaymentRequest;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierCredit;
use App\Models\SupplierPayment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierFinanceController extends Controller
{
    public function statement(Request $request, Merchant $merchant, Store $store, Supplier $supplier, RoleAuthorizer $authorizer, SupplierStatement $statement): JsonResponse
    {
        $scope = $request->query('scope', 'store');
        abort_unless(in_array($scope, ['store', 'merchant'], true), 422, 'Statement scope must be store or merchant.');
        $authorizer->authorize($this->user($request), $merchant, $scope === 'merchant' ? Permission::ReportConsolidated : Permission::PurchasingManage, $scope === 'merchant' ? null : $store);
        $this->assertScope($merchant, $store, $supplier);

        return response()->json(['data' => ['supplier' => ['id' => $supplier->public_id, 'code' => $supplier->code, 'name' => $supplier->name], 'scope' => $scope] + $statement->build($supplier, $scope === 'store' ? $store : null)]);
    }

    public function storePayment(PostSupplierPaymentRequest $request, Merchant $merchant, Store $store, Supplier $supplier, RoleAuthorizer $authorizer, PostSupplierPayment $poster): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($merchant, $store, $supplier);
        $data = $request->validated();
        $result = $poster->handle($merchant, $store, $supplier, $user, (string) $data['idempotency_key'], (string) $data['amount'], (string) $data['payment_method'], (string) $data['reference'], (string) $data['paid_at'], $data['notes'] ?? null, $data['allocations']);

        return response()->json(['data' => $this->payment($result['payment']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function reversePayment(ReverseSupplierPaymentRequest $request, Merchant $merchant, Store $store, SupplierPayment $supplierPayment, RoleAuthorizer $authorizer, ReverseSupplierPayment $reverser): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        abort_unless($supplierPayment->merchant_id === $merchant->id && $supplierPayment->store_id === $store->id, 404);
        $data = $request->validated();
        $result = $reverser->handle($supplierPayment, $user, (string) $data['idempotency_key'], (string) $data['reason'], (string) $data['reversed_at']);

        return response()->json(['data' => $this->payment($result['payment']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function confirmCredit(ConfirmSupplierCreditRequest $request, Merchant $merchant, Store $store, SupplierCredit $supplierCredit, RoleAuthorizer $authorizer, ConfirmSupplierCredit $confirmer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        abort_unless($supplierCredit->merchant_id === $merchant->id && $supplierCredit->store_id === $store->id, 404);
        $credit = $confirmer->handle($supplierCredit, $user, (string) $request->validated('supplier_reference'));

        return response()->json(['data' => ['id' => $credit->public_id, 'status' => $credit->status->value, 'amount' => $credit->amount, 'supplier_reference' => $credit->supplier_reference]]);
    }

    /** @return array<string, mixed> */
    private function payment(SupplierPayment $payment): array
    {
        $payment->loadMissing(['supplier', 'allocations.charge']);
        $allocated = $payment->allocations->reduce(fn (BigDecimal $sum, $allocation): BigDecimal => $sum->plus((string) $allocation->amount), BigDecimal::zero());

        return ['id' => $payment->public_id, 'number' => $payment->number, 'entry_kind' => $payment->entry_kind, 'supplier' => ['id' => $payment->supplier->public_id, 'name' => $payment->supplier->name], 'currency' => 'PKR', 'amount' => $payment->amount, 'allocated_amount' => (string) $allocated->toScale(2), 'unallocated_amount' => (string) BigDecimal::of($payment->amount)->minus($allocated)->toScale(2), 'payment_method' => $payment->payment_method, 'reference' => $payment->reference, 'notes' => $payment->notes, 'paid_at' => $payment->paid_at->toAtomString(), 'allocations' => $payment->allocations->map(fn ($allocation): array => ['id' => $allocation->public_id, 'supplier_charge_id' => $allocation->charge->public_id, 'source_reference' => $allocation->charge->source_reference, 'entry_kind' => $allocation->entry_kind, 'amount' => $allocation->amount])->all()];
    }

    private function assertScope(Merchant $merchant, Store $store, Supplier $supplier): void
    {
        abort_unless($store->merchant_id === $merchant->id && $supplier->merchant_id === $merchant->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
