<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Store;
use App\Models\StoreAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateMembership
{
    /** @param array{name: string, email: string, role: string, pin: string, store_ids: list<string>} $data */
    public function handle(Merchant $merchant, array $data): MerchantMembership
    {
        $stores = collect($data['store_ids'])->map(function (string $publicId) use ($merchant): Store {
            $store = (new Store)->resolveRouteBinding($publicId);
            abort_unless($store instanceof Store && $store->merchant_id === $merchant->id, 404);

            return $store;
        });

        $existingUser = User::query()->where('email', $data['email'])->first();
        if ($existingUser !== null && $existingUser->merchantMemberships()->where('merchant_id', $merchant->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['This user already belongs to the merchant.']]);
        }

        $createdUser = $existingUser === null;
        $membership = DB::transaction(function () use ($merchant, $data, $stores, $existingUser): MerchantMembership {
            $user = $existingUser ?? User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'is_active' => true,
            ]);
            abort_unless($user->is_active, 422, 'The user account is inactive.');

            $membership = MerchantMembership::query()->create([
                'merchant_id' => $merchant->id,
                'user_id' => $user->id,
                'role' => MembershipRole::from($data['role']),
                'status' => MembershipStatus::Active,
                'pin_hash' => $data['pin'],
                'pin_changed_at' => now(),
            ]);
            foreach ($stores as $store) {
                StoreAssignment::query()->create([
                    'merchant_id' => $merchant->id,
                    'merchant_membership_id' => $membership->id,
                    'store_id' => $store->id,
                ]);
            }

            return $membership;
        });

        if ($createdUser) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return $membership->load(['user', 'storeAssignments.store']);
    }
}
