<?php

namespace App\Domain\Loyalty;

use App\Domain\Customers\PakistanPhoneNormalizer;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyPublicEnrollment;
use App\Models\LoyaltyReward;
use App\Models\PublicLoyaltyAccessEvent;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PublicLoyaltyService
{
    public function __construct(private readonly PakistanPhoneNormalizer $phones) {}

    public function program(string $slug): LoyaltyProgram
    {
        $program = LoyaltyProgram::query()->with(['rewards' => fn ($query) => $query->where('is_active', true)])->where('slug', $slug)->where('is_enabled', true)->first();
        abort_unless($program instanceof LoyaltyProgram, 404, 'This loyalty programme is unavailable.');

        return $program;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{member:LoyaltyMember,already_member:bool,replayed:bool}
     */
    public function join(LoyaltyProgram $program, string $idempotencyKey, array $data): array
    {
        $phone = $this->phones->normalize((string) $data['phone']);
        abort_unless(is_string($phone), 422, 'Enter a Pakistan phone number.');
        $storeId = null;
        if (isset($data['store_id'])) {
            $store = (new Store)->resolveRouteBinding((string) $data['store_id']);
            abort_unless($store instanceof Store && $store->merchant_id === $program->merchant_id && $store->is_active, 422, 'The selected store is unavailable for this programme.');
            $storeId = $store->id;
        }
        $canonical = ['phone' => $phone, 'display_name' => trim((string) ($data['display_name'] ?? '')), 'store_id' => $storeId, 'marketing_consent' => (bool) ($data['marketing_consent'] ?? false)];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($program, $idempotencyKey, $canonical, $hash): array {
            LoyaltyProgram::query()->lockForUpdate()->findOrFail($program->id);
            $existingCommand = LoyaltyPublicEnrollment::query()->where('merchant_id', $program->merchant_id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existingCommand instanceof LoyaltyPublicEnrollment) {
                if (! hash_equals($existingCommand->payload_hash, $hash)) {
                    throw new ConflictHttpException('The enrollment identity was reused with different data.');
                }

                return ['member' => LoyaltyMember::query()->findOrFail($existingCommand->loyalty_member_id), 'already_member' => true, 'replayed' => true];
            }
            $member = LoyaltyMember::query()->where('merchant_id', $program->merchant_id)->where('canonical_phone', $canonical['phone'])->lockForUpdate()->first();
            $already = $member instanceof LoyaltyMember;
            if (! $member instanceof LoyaltyMember) {
                $name = $canonical['display_name'] !== '' ? $canonical['display_name'] : 'Member '.substr($canonical['phone'], -4);
                $member = LoyaltyMember::query()->create(['merchant_id' => $program->merchant_id, 'name' => $name, 'canonical_phone' => $canonical['phone']]);
                LoyaltyPointBalance::query()->create(['merchant_id' => $program->merchant_id, 'loyalty_member_id' => $member->id, 'balance' => '0.0000', 'version' => 0]);
            }
            LoyaltyPublicEnrollment::query()->create(['merchant_id' => $program->merchant_id, 'loyalty_program_id' => $program->id, 'loyalty_member_id' => $member->id, 'attributed_store_id' => $canonical['store_id'], 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'marketing_consent' => $canonical['marketing_consent'], 'joined_at' => now()]);

            return ['member' => $member, 'already_member' => $already, 'replayed' => false];
        }, attempts: 3);
    }

    public function lookup(LoyaltyProgram $program, string $phone): ?LoyaltyMember
    {
        $normalized = $this->phones->normalize($phone);
        if ($normalized === null) {
            return null;
        }

        return LoyaltyMember::query()->with('pointBalance')->where('merchant_id', $program->merchant_id)->where('canonical_phone', $normalized)->first();
    }

    /** @return array<string,mixed> */
    public function publicMember(LoyaltyProgram $program, LoyaltyMember $member): array
    {
        abort_unless($member->merchant_id === $program->merchant_id, 404);
        $balance = $member->relationLoaded('pointBalance') ? $member->getRelation('pointBalance') : $member->pointBalance()->first();
        $points = $balance instanceof LoyaltyPointBalance ? $balance->balance : '0.0000';
        $rewards = LoyaltyReward::query()->where('loyalty_program_id', $program->id)->where('is_active', true)->where('points_required', '<=', $points)->orderBy('points_required')->get();

        return ['member_public_id' => $member->public_id, 'masked_name' => $this->maskName($member->name), 'masked_phone' => $this->maskPhone($member->canonical_phone), 'points_balance' => $points, 'available_rewards' => $rewards->map(fn (LoyaltyReward $reward): array => ['public_id' => $reward->public_id, 'name' => $reward->name, 'points_required' => $reward->points_required])->all()];
    }

    public function audit(LoyaltyProgram $program, string $action, string $outcome, ?string $phone, string $ip, ?string $userAgent): void
    {
        $key = (string) config('app.key');
        $normalized = null;
        if ($phone !== null) {
            try {
                $normalized = $this->phones->normalize($phone);
            } catch (\Throwable) {
                $normalized = trim($phone);
            }
        }
        PublicLoyaltyAccessEvent::query()->create(['merchant_id' => $program->merchant_id, 'loyalty_program_id' => $program->id, 'action' => $action, 'outcome' => $outcome, 'phone_hash' => $normalized === null ? null : hash_hmac('sha256', $normalized, $key), 'ip_hash' => hash_hmac('sha256', $ip, $key), 'user_agent_family' => $userAgent === null ? null : mb_substr($userAgent, 0, 80), 'occurred_at' => now()]);
    }

    private function maskName(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->map(fn (string $part): string => mb_substr($part, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($part) - 1))))->join(' ');
    }

    private function maskPhone(string $phone): string
    {
        return '+92 ••• ••• '.substr(preg_replace('/\D+/', '', $phone) ?: '', -4);
    }
}
