<?php

namespace App\Domain\Customers;

use App\Models\CustomerGroup;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerWriter
{
    public function __construct(private readonly CustomerScope $scope, private readonly PakistanPhoneNormalizer $phones) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, Merchant $merchant, Store $store, array $data): OperationalCustomer
    {
        $scopeStoreId = $this->scope->storeId($merchant, $store);

        return DB::transaction(function () use ($actor, $merchant, $store, $scopeStoreId, $data): OperationalCustomer {
            $normalized = $this->normalize($merchant, $scopeStoreId, $data, null);
            $reference = mb_strtoupper((string) ($data['local_reference'] ?? ('CUS-'.Str::random(10))));
            abort_if(OperationalCustomer::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('local_reference', $reference)->exists(), 422, 'A customer reference already exists in this scope.');
            $customer = OperationalCustomer::query()->create($normalized + ['merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'local_reference' => $reference, 'version' => 1]);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'customer.created', 'outcome' => 'succeeded', 'metadata' => ['customer_id' => $customer->public_id, 'scope' => $scopeStoreId === null ? 'merchant' : 'store'], 'occurred_at' => now()]);

            return $customer->load(['customerGroup', 'loyaltyLink.loyaltyMember', 'store']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Merchant $merchant, Store $store, OperationalCustomer $customer, array $data): OperationalCustomer
    {
        $scopeStoreId = $this->scope->storeId($merchant, $store);
        abort_unless($customer->merchant_id === $merchant->id && $customer->store_id === $scopeStoreId, 404);

        return DB::transaction(function () use ($actor, $merchant, $store, $scopeStoreId, $customer, $data): OperationalCustomer {
            $locked = OperationalCustomer::query()->lockForUpdate()->findOrFail($customer->id);
            abort_unless($locked->version === (int) $data['expected_version'], 409, 'The customer changed. Reload before saving.');
            $changes = $this->normalize($merchant, $scopeStoreId, $data, $locked);
            if (array_key_exists('archived', $data)) {
                $changes['archived_at'] = $data['archived'] ? now() : null;
            }
            $changes['version'] = $locked->version + 1;
            $locked->update($changes);
            SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $actor->id, 'event_code' => 'customer.updated', 'outcome' => 'succeeded', 'metadata' => ['customer_id' => $locked->public_id, 'version' => $locked->version, 'changed_keys' => array_keys($changes)], 'occurred_at' => now()]);

            return $locked->refresh()->load(['customerGroup', 'loyaltyLink.loyaltyMember', 'store']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(Merchant $merchant, ?int $scopeStoreId, array $data, ?OperationalCustomer $existing): array
    {
        $changes = Arr::only($data, ['name', 'address_line', 'city', 'postal_code', 'email_receipt_preference', 'marketing_email_consent', 'marketing_consent_source']);
        if (array_key_exists('phone', $data)) {
            $changes['phone'] = $data['phone'];
            $changes['normalized_phone'] = $this->phones->normalize(is_string($data['phone']) ? $data['phone'] : null);
        }
        if (array_key_exists('email', $data)) {
            $changes['email'] = $data['email'];
            $changes['normalized_email'] = is_string($data['email']) ? mb_strtolower(trim($data['email'])) : null;
        }
        if (array_key_exists('customer_group_id', $data)) {
            $changes['customer_group_id'] = $this->groupId($merchant, $scopeStoreId, $data['customer_group_id']);
        }
        $email = $changes['normalized_email'] ?? $existing?->normalized_email;
        $receipt = $changes['email_receipt_preference'] ?? ($existing instanceof OperationalCustomer ? $existing->email_receipt_preference : false);
        $marketing = $changes['marketing_email_consent'] ?? ($existing instanceof OperationalCustomer ? $existing->marketing_email_consent : false);
        abort_if(($receipt || $marketing) && $email === null, 422, 'An email address is required for email preferences.');
        if (array_key_exists('marketing_email_consent', $changes)) {
            if ($marketing) {
                abort_if(empty($changes['marketing_consent_source']), 422, 'Record the source of marketing consent.');
                $changes['marketing_consent_at'] = now();
            } else {
                $changes['marketing_consent_at'] = null;
                $changes['marketing_consent_source'] = null;
            }
        }

        return $changes;
    }

    private function groupId(Merchant $merchant, ?int $scopeStoreId, mixed $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }
        $group = (new CustomerGroup)->resolveRouteBinding((string) $publicId);
        abort_unless($group instanceof CustomerGroup && $group->merchant_id === $merchant->id && $group->store_id === $scopeStoreId && $group->archived_at === null, 422, 'The customer group is outside this customer scope.');

        return $group->id;
    }
}
