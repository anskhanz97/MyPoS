<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Cash\Enums\CashMovementKind;
use App\Domain\Cash\HandoverCashSession;
use App\Domain\Cash\OpenCashSession;
use App\Domain\Cash\PostCashMovement;
use App\Domain\Cash\ReviewCashSession;
use App\Domain\Cash\SubmitCashSessionCount;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\HandoverCashSessionRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Http\Requests\PostCashMovementRequest;
use App\Http\Requests\ReviewCashSessionRequest;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::CashOperate, $store);
        $this->scope($merchant, $store, $register);
        $sessions = CashSession::query()->with('register')->where('register_id', $register->id)->latest('opened_at')->limit(100)->get();

        return response()->json(['data' => $sessions->map(fn (CashSession $session): array => $this->present($session))]);
    }

    public function store(OpenCashSessionRequest $request, Merchant $merchant, Store $store, Register $register, RoleAuthorizer $authorizer, OpenCashSession $opener): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::CashOperate, $store);
        $this->scope($merchant, $store, $register);
        $data = $request->validated();
        $result = $opener->handle($merchant, $store, $register, $user, (string) $data['idempotency_key'], (string) $data['opening_amount'], (string) $data['opened_at']);

        return response()->json(['data' => $this->present($result['session']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::CashOperate, $store);
        $this->sessionScope($merchant, $store, $register, $cashSession);

        return response()->json(['data' => $this->present($cashSession, true)]);
    }

    public function movement(PostCashMovementRequest $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer, PostCashMovement $poster): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validated();
        $kind = CashMovementKind::from((string) $data['kind']);
        $permission = match ($kind) {
            CashMovementKind::PaidOut, CashMovementKind::SupplierPayment => Permission::CashManage, CashMovementKind::NoSaleOpen => Permission::DrawerNoSale, default => Permission::CashOperate
        };
        $authorizer->authorize($user, $merchant, $permission, $store);
        $this->sessionScope($merchant, $store, $register, $cashSession);
        $result = $poster->handle($cashSession, $user, (string) $data['idempotency_key'], $kind, (string) $data['amount'], (string) $data['reason'], (string) $data['occurred_at'], $data['source_type'] ?? null, $data['source_id'] ?? null);

        return response()->json(['data' => $this->movementData($result['movement']), 'cash_session' => $this->present($result['session']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function handover(HandoverCashSessionRequest $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer, HandoverCashSession $handover): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::CashOperate, $store);
        $this->sessionScope($merchant, $store, $register, $cashSession);
        $data = $request->validated();
        $membership = (new MerchantMembership)->resolveRouteBinding((string) $data['to_membership_id']);
        abort_unless($membership instanceof MerchantMembership && $membership->merchant_id === $merchant->id, 422, 'Target staff membership was not found.');
        $to = $membership->user;
        $result = $handover->handle($cashSession, $user, $to, (string) $data['idempotency_key'], $data['note'] ?? null);

        return response()->json(['data' => $this->present($result['session']), 'handover_id' => $result['handover']->public_id, 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function close(CloseCashSessionRequest $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer, SubmitCashSessionCount $closer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::CashOperate, $store);
        $this->sessionScope($merchant, $store, $register, $cashSession);
        $data = $request->validated();
        $result = $closer->handle($cashSession, $user, (string) $data['idempotency_key'], (int) $data['expected_version'], (string) $data['counted_amount'], $data['denominations'] ?? null, (int) $data['pending_server_sale_count'], (int) $data['pending_offline_sale_count']);

        return response()->json(['data' => $this->present($result['session']), 'count_id' => $result['count']->public_id, 'replayed' => $result['replayed']]);
    }

    public function review(ReviewCashSessionRequest $request, Merchant $merchant, Store $store, Register $register, CashSession $cashSession, RoleAuthorizer $authorizer, ReviewCashSession $reviewer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::CashVarianceApprove, $store);
        $this->sessionScope($merchant, $store, $register, $cashSession);
        $data = $request->validated();
        $result = $reviewer->handle($cashSession, $user, (string) $data['idempotency_key'], (int) $data['expected_version'], (string) $data['decision'], (string) $data['reason']);

        return response()->json(['data' => $this->present($result['session']), 'review_id' => $result['review']->public_id, 'replayed' => $result['replayed']]);
    }

    /** @return array<string, mixed> */
    private function present(CashSession $session, bool $details = false): array
    {
        $data = ['id' => $session->public_id, 'number' => $session->number, 'register_id' => $session->register->public_id, 'state' => $session->status->value, 'currency' => 'PKR', 'business_date' => $session->business_date->toDateString(), 'opening_amount' => $session->opening_amount, 'expected_amount' => $session->expected_amount, 'counted_amount' => $session->counted_amount, 'variance' => $session->variance_amount, 'version' => $session->version, 'handover_count' => $session->handover_count, 'pending_server_sale_count' => $session->pending_server_sale_count, 'pending_offline_sale_count' => $session->pending_offline_sale_count, 'opened_at' => $session->opened_at->toAtomString(), 'close_cutoff_at' => $session->close_cutoff_at?->toAtomString(), 'closed_at' => $session->closed_at?->toAtomString()];
        if ($details) {
            $data['movements'] = $session->movements()->orderBy('id')->get()->map($this->movementData(...))->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function movementData(CashMovement $movement): array
    {
        return ['id' => $movement->public_id, 'kind' => $movement->kind->value, 'signed_amount' => $movement->signed_amount, 'reason' => $movement->reason, 'source_type' => $movement->source_type, 'source_id' => $movement->source_public_id, 'occurred_at' => $movement->occurred_at->toAtomString()];
    }

    private function scope(Merchant $merchant, Store $store, Register $register): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 404);
    }

    private function sessionScope(Merchant $merchant, Store $store, Register $register, CashSession $session): void
    {
        $this->scope($merchant, $store, $register);
        abort_unless($session->merchant_id === $merchant->id && $session->store_id === $store->id && $session->register_id === $register->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
