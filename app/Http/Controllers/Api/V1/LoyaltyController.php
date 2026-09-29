<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Customers\PakistanPhoneNormalizer;
use App\Domain\Loyalty\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyPointEntry;
use App\Models\LoyaltyProgram;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function program(Request $request, Merchant $merchant, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::LoyaltyManage);
        $program = LoyaltyProgram::query()->with(['versions' => fn ($q) => $q->latest('version'), 'rewards'])->where('merchant_id', $merchant->id)->first();

        return response()->json(['data' => $program === null ? null : $this->programData($program)]);
    }

    public function publish(Request $request, Merchant $merchant, RoleAuthorizer $authorizer, LoyaltyService $loyalty): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::LoyaltyManage);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'slug' => ['required', 'alpha_dash', 'max:80'],
            'points_per_pkr' => ['required', 'decimal:0,8', 'gt:0'], 'expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'rewards' => ['required', 'array', 'max:50'], 'rewards.*.name' => ['required', 'string', 'max:160'],
            'rewards.*.points_required' => ['required', 'decimal:0,4', 'gt:0'], 'rewards.*.discount_amount' => ['required', 'decimal:0,2', 'gt:0'], 'rewards.*.is_active' => ['sometimes', 'boolean'],
        ]);
        $program = $loyalty->publish($merchant, $user, $data['name'], $data['slug'], $data['points_per_pkr'], $data['expiry_days'] ?? null, $data['rewards']);

        return response()->json(['data' => $this->programData($program)], 201);
    }

    public function members(Request $request, Merchant $merchant, RoleAuthorizer $authorizer, PakistanPhoneNormalizer $phones): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleCreate);
        $validated = $request->validate(['phone' => ['nullable', 'string', 'max:32'], 'query' => ['nullable', 'string', 'max:120']]);
        $phone = $phones->normalize($validated['phone'] ?? null);
        $query = LoyaltyMember::query()->with('pointBalance')->where('merchant_id', $merchant->id)
            ->when($phone !== null, fn ($q) => $q->where('canonical_phone', $phone))
            ->when(isset($validated['query']), fn ($q) => $q->where('name', 'like', '%'.addcslashes($validated['query'], '%_').'%'))
            ->latest()->limit(50)->get();

        return response()->json(['data' => $query->map($this->memberData(...))]);
    }

    public function createMember(Request $request, Merchant $merchant, RoleAuthorizer $authorizer, PakistanPhoneNormalizer $phones): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::LoyaltyManage);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'phone' => ['required', 'string', 'max:32']]);
        $phone = $phones->normalize($data['phone']);
        abort_if(LoyaltyMember::query()->where('merchant_id', $merchant->id)->where('canonical_phone', $phone)->exists(), 422, 'A loyalty member already uses this phone for the merchant.');
        $member = DB::transaction(function () use ($merchant, $data, $phone): LoyaltyMember {
            $member = LoyaltyMember::query()->create(['merchant_id' => $merchant->id, 'name' => $data['name'], 'canonical_phone' => $phone]);
            LoyaltyPointBalance::query()->create(['merchant_id' => $merchant->id, 'loyalty_member_id' => $member->id, 'balance' => '0.0000', 'version' => 0]);

            return $member->load('pointBalance');
        });

        return response()->json(['data' => $this->memberData($member)], 201);
    }

    public function member(Request $request, Merchant $merchant, LoyaltyMember $loyaltyMember, RoleAuthorizer $authorizer): JsonResponse
    {
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleCreate);
        abort_unless($loyaltyMember->merchant_id === $merchant->id, 404);
        $loyaltyMember->load(['pointBalance', 'pointEntries' => fn ($q) => $q->latest('occurred_at')->limit(100)]);

        return response()->json(['data' => $this->memberData($loyaltyMember) + ['ledger' => $loyaltyMember->pointEntries->map(fn (LoyaltyPointEntry $entry) => ['id' => $entry->public_id, 'kind' => $entry->kind, 'points' => $entry->points, 'source_type' => $entry->source_type, 'source_reference' => $entry->source_reference, 'reason' => $entry->reason, 'expires_at' => $entry->expires_at?->toAtomString(), 'occurred_at' => $entry->occurred_at->toAtomString()])->all()]]);
    }

    public function adjust(Request $request, Merchant $merchant, LoyaltyMember $loyaltyMember, RoleAuthorizer $authorizer, LoyaltyService $loyalty): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::LoyaltyAdjust);
        abort_unless($loyaltyMember->merchant_id === $merchant->id, 404);
        $data = $request->validate(['points' => ['required', 'regex:/^-?[0-9]{1,15}(\.[0-9]{1,4})?$/', 'not_in:0,0.0,0.00,0.000,0.0000'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[0-9a-f-]{36}$/i', $key) === 1, 422, 'A UUID Idempotency-Key header is required.');
        $entry = $loyalty->adjust($merchant, $loyaltyMember, $user, $key, $data['points'], $data['reason']);

        return response()->json(['data' => ['id' => $entry->public_id, 'kind' => $entry->kind, 'points' => $entry->points, 'reason' => $entry->reason]], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array<string,mixed> */
    private function memberData(LoyaltyMember $member): array
    {
        $balance = $member->relationLoaded('pointBalance') ? $member->getRelation('pointBalance') : $member->pointBalance()->first();
        if (! $balance instanceof LoyaltyPointBalance) {
            return ['id' => $member->public_id, 'name' => $member->name, 'phone' => $member->canonical_phone, 'balance' => '0.0000', 'balance_version' => 0];
        }

        return ['id' => $member->public_id, 'name' => $member->name, 'phone' => $member->canonical_phone, 'balance' => $balance->balance, 'balance_version' => $balance->version];
    }

    /** @return array<string,mixed> */
    private function programData(LoyaltyProgram $program): array
    {
        $version = $program->versions->first();

        return ['id' => $program->public_id, 'name' => $program->name, 'slug' => $program->slug, 'enabled' => $program->is_enabled, 'version' => $program->version, 'points_per_pkr' => $version?->points_per_pkr, 'expiry_days' => $version?->expiry_days, 'rewards' => $program->rewards->map(fn ($reward) => ['id' => $reward->public_id, 'name' => $reward->name, 'points_required' => $reward->points_required, 'discount_amount' => $reward->discount_amount, 'active' => $reward->is_active])->all()];
    }
}
