<?php

namespace App\Domain\Loyalty;

use App\Domain\Shared\Math\Decimal;
use App\Models\Cart;
use App\Models\LoyaltyCustomerLink;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointBalance;
use App\Models\LoyaltyPointConsumption;
use App\Models\LoyaltyPointEntry;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramVersion;
use App\Models\LoyaltyRedemptionReservation;
use App\Models\LoyaltyReward;
use App\Models\Merchant;
use App\Models\OutboxEvent;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class LoyaltyService
{
    /** @param list<array{name:string,points_required:string,discount_amount:string,is_active?:bool}> $rewards */
    public function publish(Merchant $merchant, User $actor, string $name, string $slug, string $rate, ?int $expiryDays, array $rewards): LoyaltyProgram
    {
        return DB::transaction(function () use ($merchant, $actor, $name, $slug, $rate, $expiryDays, $rewards): LoyaltyProgram {
            $program = LoyaltyProgram::query()->where('merchant_id', $merchant->id)->lockForUpdate()->first();
            if (! $program instanceof LoyaltyProgram) {
                $program = LoyaltyProgram::query()->create(['merchant_id' => $merchant->id, 'slug' => $slug, 'name' => $name, 'is_enabled' => true, 'version' => 1]);
            } else {
                $program->forceFill(['name' => $name, 'slug' => $slug, 'is_enabled' => true, 'version' => $program->version + 1])->save();
            }
            LoyaltyProgramVersion::query()->create(['merchant_id' => $merchant->id, 'loyalty_program_id' => $program->id, 'version' => $program->version, 'points_per_pkr' => Decimal::fixed(BigDecimal::of($rate), 8), 'expiry_days' => $expiryDays, 'published_by_user_id' => $actor->id, 'effective_at' => now()]);
            foreach ($program->rewards()->get() as $reward) {
                $reward->forceFill(['is_active' => false, 'version' => $reward->version + 1])->save();
            }
            foreach ($rewards as $input) {
                LoyaltyReward::query()->create(['merchant_id' => $merchant->id, 'loyalty_program_id' => $program->id, 'name' => $input['name'], 'points_required' => $input['points_required'], 'discount_amount' => $input['discount_amount'], 'is_active' => $input['is_active'] ?? true, 'version' => 1]);
            }

            return $program->refresh()->load(['versions' => fn ($q) => $q->latest('version'), 'rewards']);
        });
    }

    /** @return array{reservation:LoyaltyRedemptionReservation,replayed:bool} */
    public function reserve(Merchant $merchant, Store $store, Cart $cart, User $actor, string $idempotencyKey, string $rewardPublicId, int $expectedCartVersion): array
    {
        $payloadHash = hash('sha256', json_encode([$cart->public_id, $rewardPublicId, $expectedCartVersion], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($merchant, $store, $cart, $actor, $idempotencyKey, $rewardPublicId, $expectedCartVersion, $payloadHash): array {
            $existing = LoyaltyRedemptionReservation::query()->where('merchant_id', $merchant->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof LoyaltyRedemptionReservation) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new ConflictHttpException('The loyalty reservation identity was reused with different data.');
                }

                return ['reservation' => $existing, 'replayed' => true];
            }
            $locked = Cart::query()->with('customer.loyaltyLink.loyaltyMember')->lockForUpdate()->findOrFail($cart->id);
            abort_unless($locked->merchant_id === $merchant->id && $locked->store_id === $store->id, 404);
            abort_unless($locked->version === $expectedCartVersion, 409, 'The cart changed. Reload it before reserving points.');
            $member = $locked->customer?->loyaltyLink?->loyaltyMember;
            abort_unless($member instanceof LoyaltyMember && $member->merchant_id === $merchant->id, 422, 'Link a merchant loyalty member to the cart customer first.');
            $reward = (new LoyaltyReward)->resolveRouteBinding($rewardPublicId);
            abort_unless($reward instanceof LoyaltyReward && $reward->merchant_id === $merchant->id && $reward->is_active, 422, 'The loyalty reward is unavailable.');
            $balance = $this->lockedBalance($member);
            $reserved = BigDecimal::zero();
            $activeReservations = LoyaltyRedemptionReservation::query()->where('loyalty_member_id', $member->id)->where('state', 'reserved')->where('expires_at', '>', now())->lockForUpdate()->get();
            foreach ($activeReservations as $activeReservation) {
                $reserved = $reserved->plus($activeReservation->points);
            }
            abort_if(BigDecimal::of($balance->balance)->minus($reserved)->isLessThan(BigDecimal::of($reward->points_required)), 409, 'The member does not have enough available points.');
            $this->releaseCart($locked);
            $reservation = LoyaltyRedemptionReservation::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'loyalty_member_id' => $member->id, 'loyalty_reward_id' => $reward->id, 'cart_id' => $locked->id, 'created_by_user_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash, 'state' => 'reserved', 'points' => $reward->points_required, 'discount_amount' => $reward->discount_amount, 'expires_at' => now()->addMinutes(5)]);
            $locked->forceFill(['pricing_version' => null, 'version' => $locked->version + 1])->save();

            return ['reservation' => $reservation, 'replayed' => false];
        }, attempts: 3);
    }

    public function releaseCart(Cart $cart): void
    {
        LoyaltyRedemptionReservation::query()->where('cart_id', $cart->id)->where('state', 'reserved')->update(['state' => 'released', 'released_at' => now()]);
    }

    /** @return array{reservation_id:string,points:string,discount_amount:string}|null */
    public function quoteData(Cart $cart): ?array
    {
        $reservation = LoyaltyRedemptionReservation::query()->where('cart_id', $cart->id)->where('state', 'reserved')->where('expires_at', '>', now())->first();
        if (! $reservation instanceof LoyaltyRedemptionReservation) {
            return null;
        }

        return ['reservation_id' => $reservation->public_id, 'points' => $reservation->points, 'discount_amount' => $reservation->discount_amount];
    }

    /** @param array<string,mixed> $quotePayload */
    public function commit(Sale $sale, Cart $cart, array $quotePayload, User $actor, string $source): void
    {
        $quoted = $quotePayload['loyalty_redemption'] ?? null;
        if ($source === 'offline') {
            abort_if(is_array($quoted), 422, 'Loyalty redemption requires an online checkout.');

            return;
        }
        $reservation = LoyaltyRedemptionReservation::query()->where('cart_id', $cart->id)->lockForUpdate()->first();
        if (! is_array($quoted)) {
            abort_if($reservation instanceof LoyaltyRedemptionReservation && $reservation->state === 'reserved', 409, 'Quote the cart again with its loyalty reservation.');

            return;
        }
        abort_unless($reservation instanceof LoyaltyRedemptionReservation && $reservation->public_id === ($quoted['reservation_id'] ?? null) && $reservation->state === 'reserved' && $reservation->expires_at > now(), 409, 'The loyalty reservation expired or changed.');
        $member = LoyaltyMember::query()->findOrFail($reservation->loyalty_member_id);
        $debit = $this->post($member, $sale->store_id, null, $actor, 'redeem', BigDecimal::of($reservation->points)->negated(), 'sale_redemption', $sale->public_id, 'Reward redeemed');
        $this->consumeEarns($member, $debit, BigDecimal::of($reservation->points));
        $reservation->forceFill(['state' => 'committed', 'sale_id' => $sale->id, 'committed_at' => now()])->save();
    }

    public function earn(Sale $sale, User $actor): ?LoyaltyPointEntry
    {
        if ($sale->customer_id === null) {
            return null;
        }
        $link = LoyaltyCustomerLink::query()->where('operational_customer_id', $sale->customer_id)->first();
        $program = LoyaltyProgram::query()->where('merchant_id', $sale->merchant_id)->where('is_enabled', true)->first();
        if (! $link instanceof LoyaltyCustomerLink || ! $program instanceof LoyaltyProgram) {
            return null;
        }
        $version = LoyaltyProgramVersion::query()->where('loyalty_program_id', $program->id)->where('effective_at', '<=', $sale->finalized_at)->latest('version')->first();
        if (! $version instanceof LoyaltyProgramVersion) {
            return null;
        }
        $points = BigDecimal::of($sale->net_total)->multipliedBy($version->points_per_pkr)->toScale(4, RoundingMode::Down);
        if (! $points->isPositive()) {
            return null;
        }
        $member = LoyaltyMember::query()->findOrFail($link->loyalty_member_id);

        return $this->post($member, $sale->store_id, $version, $actor, 'earn', $points, 'sale', $sale->public_id, null, $sale->net_total, $version->expiry_days === null ? null : $sale->finalized_at->addDays($version->expiry_days));
    }

    public function reverseReturn(SaleReturn $return, User $actor): ?LoyaltyPointEntry
    {
        $earn = LoyaltyPointEntry::query()->where('merchant_id', $return->merchant_id)->where('kind', 'earn')->where('source_type', 'sale')->where('source_reference', $return->sale->public_id)->first();
        if (! $earn instanceof LoyaltyPointEntry || BigDecimal::of($return->sale->net_total)->isZero()) {
            return null;
        }
        $returnedNet = BigDecimal::of((string) SaleReturn::query()->where('sale_id', $return->sale_id)->whereIn('state', ['posted', 'refund_pending'])->sum('net_total'));
        $entitled = BigDecimal::of($earn->points)->multipliedBy($returnedNet)->dividedBy(BigDecimal::of($return->sale->net_total), 4, RoundingMode::Down);
        $already = BigDecimal::of((string) LoyaltyPointEntry::query()->where('related_entry_id', $earn->id)->where('kind', 'refund_reversal')->sum('points'))->abs();
        $delta = $entitled->minus($already)->toScale(4);
        if (! $delta->isPositive()) {
            return null;
        }
        $member = LoyaltyMember::query()->findOrFail($earn->loyalty_member_id);

        return $this->post($member, $return->store_id, null, $actor, 'refund_reversal', $delta->negated(), 'sale_return', $return->public_id, 'Points reversed for returned value', null, null, $earn);
    }

    public function expire(): int
    {
        $count = 0;
        LoyaltyPointEntry::query()->where('kind', 'earn')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($entries) use (&$count): void {
            foreach ($entries as $earn) {
                DB::transaction(function () use ($earn, &$count): void {
                    if (LoyaltyPointEntry::query()->where('merchant_id', $earn->merchant_id)->where('kind', 'expire')->where('source_type', 'earn')->where('source_reference', $earn->public_id)->exists()) {
                        return;
                    }
                    $member = LoyaltyMember::query()->findOrFail($earn->loyalty_member_id);
                    $balance = $this->lockedBalance($member);
                    $consumed = BigDecimal::of((string) LoyaltyPointConsumption::query()->where('earn_entry_id', $earn->id)->sum('points'));
                    $alreadyExpired = BigDecimal::of((string) LoyaltyPointEntry::query()->where('related_entry_id', $earn->id)->where('kind', 'expire')->sum('points'))->abs();
                    $remaining = BigDecimal::of($earn->points)->minus($consumed)->minus($alreadyExpired);
                    $balanceValue = BigDecimal::of($balance->balance);
                    $amount = ! $remaining->isPositive() || ! $balanceValue->isPositive()
                        ? BigDecimal::zero()
                        : ($remaining->isLessThan($balanceValue) ? $remaining : $balanceValue);
                    $this->post($member, null, null, null, 'expire', $amount->negated(), 'earn', $earn->public_id, 'Earned points expired', null, null, $earn);
                    $count++;
                });
            }
        });

        return $count;
    }

    public function adjust(Merchant $merchant, LoyaltyMember $member, User $actor, string $idempotencyKey, string $points, string $reason): LoyaltyPointEntry
    {
        abort_unless($member->merchant_id === $merchant->id, 404);
        $amount = BigDecimal::of($points)->toScale(4);
        abort_if($amount->isZero(), 422, 'A loyalty adjustment cannot be zero.');

        return DB::transaction(function () use ($member, $actor, $idempotencyKey, $amount, $reason): LoyaltyPointEntry {
            $existing = LoyaltyPointEntry::query()->where('merchant_id', $member->merchant_id)->where('kind', 'manual_adjustment')->where('source_type', 'manual_adjustment')->where('source_reference', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof LoyaltyPointEntry) {
                if (! BigDecimal::of($existing->points)->isEqualTo($amount) || $existing->reason !== $reason) {
                    throw new ConflictHttpException('The loyalty adjustment identity was reused with different data.');
                }

                return $existing;
            }

            return $this->post($member, null, null, $actor, 'manual_adjustment', $amount, 'manual_adjustment', $idempotencyKey, $reason);
        }, attempts: 3);
    }

    /** @return array{checked:int,mismatched:int,repaired:int} */
    public function reconcile(bool $repair = false): array
    {
        $result = ['checked' => 0, 'mismatched' => 0, 'repaired' => 0];
        LoyaltyPointBalance::query()->orderBy('id')->chunkById(100, function ($balances) use (&$result, $repair): void {
            foreach ($balances as $balance) {
                $result['checked']++;
                $ledger = BigDecimal::of((string) LoyaltyPointEntry::query()->where('loyalty_member_id', $balance->loyalty_member_id)->sum('points'))->toScale(4);
                if ($ledger->isEqualTo(BigDecimal::of($balance->balance))) {
                    continue;
                }
                $result['mismatched']++;
                if ($repair) {
                    DB::transaction(function () use ($balance, $ledger): void {
                        $locked = LoyaltyPointBalance::query()->lockForUpdate()->findOrFail($balance->id);
                        $locked->forceFill(['balance' => Decimal::fixed($ledger, 4), 'version' => $locked->version + 1])->save();
                    });
                    $result['repaired']++;
                }
            }
        });

        return $result;
    }

    private function lockedBalance(LoyaltyMember $member): LoyaltyPointBalance
    {
        LoyaltyPointBalance::query()->firstOrCreate(['merchant_id' => $member->merchant_id, 'loyalty_member_id' => $member->id], ['balance' => '0.0000', 'version' => 0]);

        return LoyaltyPointBalance::query()->where('loyalty_member_id', $member->id)->lockForUpdate()->firstOrFail();
    }

    private function consumeEarns(LoyaltyMember $member, LoyaltyPointEntry $debit, BigDecimal $required): void
    {
        $remaining = $required;
        $earns = LoyaltyPointEntry::query()->where('loyalty_member_id', $member->id)->where('kind', 'earn')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderByRaw('expires_at is null, expires_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($earns as $earn) {
            $used = BigDecimal::of((string) LoyaltyPointConsumption::query()->where('earn_entry_id', $earn->id)->sum('points'));
            $available = BigDecimal::of($earn->points)->minus($used);
            if (! $available->isPositive()) {
                continue;
            }
            $piece = $available->isLessThan($remaining) ? $available : $remaining;
            LoyaltyPointConsumption::query()->create(['merchant_id' => $member->merchant_id, 'loyalty_member_id' => $member->id, 'debit_entry_id' => $debit->id, 'earn_entry_id' => $earn->id, 'points' => Decimal::fixed($piece, 4)]);
            $remaining = $remaining->minus($piece);
            if ($remaining->isZero()) {
                return;
            }
        }
        throw new ConflictHttpException('The member point sources no longer cover this redemption.');
    }

    private function post(LoyaltyMember $member, ?int $storeId, ?LoyaltyProgramVersion $version, ?User $actor, string $kind, BigDecimal $points, string $sourceType, string $sourceReference, ?string $reason, ?string $eligibleNet = null, ?CarbonInterface $expiresAt = null, ?LoyaltyPointEntry $related = null): LoyaltyPointEntry
    {
        $existing = LoyaltyPointEntry::query()->where('merchant_id', $member->merchant_id)->where('source_type', $sourceType)->where('source_reference', $sourceReference)->where('kind', $kind)->first();
        if ($existing instanceof LoyaltyPointEntry) {
            return $existing;
        }
        $balance = $this->lockedBalance($member);
        if ($kind === 'redeem') {
            abort_if(BigDecimal::of($balance->balance)->plus($points)->isNegative(), 409, 'The member no longer has enough points.');
        }
        $entry = LoyaltyPointEntry::query()->create(['merchant_id' => $member->merchant_id, 'store_id' => $storeId, 'loyalty_member_id' => $member->id, 'loyalty_program_version_id' => $version?->id, 'related_entry_id' => $related?->id, 'actor_user_id' => $actor?->id, 'kind' => $kind, 'points' => Decimal::fixed($points, 4), 'eligible_net' => $eligibleNet, 'source_type' => $sourceType, 'source_reference' => $sourceReference, 'reason' => $reason, 'expires_at' => $expiresAt, 'occurred_at' => now()]);
        $balance->forceFill(['balance' => Decimal::fixed(BigDecimal::of($balance->balance)->plus($points), 4), 'version' => $balance->version + 1])->save();
        OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $member->merchant_id, 'store_id' => $storeId, 'event_type' => 'loyalty.points.'.$kind, 'aggregate_type' => 'loyalty_member', 'aggregate_id' => $member->public_id, 'payload' => ['entry_id' => $entry->public_id, 'member_id' => $member->public_id, 'points' => $entry->points, 'balance' => $balance->balance], 'occurred_at' => now(), 'available_at' => now()]);

        return $entry;
    }
}
