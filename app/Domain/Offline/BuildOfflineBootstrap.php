<?php

namespace App\Domain\Offline;

use App\Domain\Auth\FixedRolePermissions;
use App\Domain\Cash\Enums\CashSessionStatus;
use App\Domain\Catalog\CatalogScope;
use App\Domain\Pricing\PricingVersionResolver;
use App\Domain\Printing\PrintAuthorizationSigner;
use App\Models\CashSession;
use App\Models\DeviceEnrollment;
use App\Models\ExtraGroup;
use App\Models\ExtraOption;
use App\Models\InventoryLocation;
use App\Models\MerchantMembership;
use App\Models\OfflineBootstrapSnapshot;
use App\Models\PriceBookVersion;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\PromotionRule;
use App\Models\UnitConversion;
use App\Models\VariantPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class BuildOfflineBootstrap
{
    public function __construct(
        private readonly CatalogScope $catalogScope,
        private readonly PricingVersionResolver $pricing,
        private readonly FixedRolePermissions $permissions,
        private readonly CanonicalPayload $canonical,
        private readonly PrintAuthorizationSigner $printSigner,
    ) {}

    /**
     * @param  list<int>  $supportedSchemaVersions
     * @return array<string, mixed>
     */
    public function handle(DeviceEnrollment $device, array $supportedSchemaVersions): array
    {
        abort_unless(in_array(1, $supportedSchemaVersions, true), 409, 'This register does not support the current offline schema.');
        $device->loadMissing(['merchant', 'store', 'register']);
        $merchant = $device->merchant;
        $store = $device->store;
        $scopeStoreId = $this->catalogScope->storeId($merchant, $store);
        $variants = ProductVariant::query()
            ->with(['product.extraGroups.options', 'baseUnit', 'barcodes', 'conversions.fromUnit'])
            ->where('merchant_id', $merchant->id)
            ->where('store_id', $scopeStoreId)
            ->whereNull('archived_at')
            ->whereHas('product', function ($query) use ($scopeStoreId, $store): void {
                $query->whereNull('archived_at');
                if ($scopeStoreId === null) {
                    $query->whereHas('storeAvailabilities', fn ($availability) => $availability->where('store_id', $store->id)->where('is_available', true));
                }
            })
            ->orderBy('id')->limit(100001)->get();
        abort_if($variants->count() > 100000, 409, 'The authorized offline catalogue exceeds the supported snapshot bound.');
        $resolved = $this->pricing->current($merchant, $store, now()->toImmutable());
        $versions = collect([$resolved['merchant'], $resolved['store']])->filter(fn ($version): bool => $version instanceof PriceBookVersion)->values();
        $versions->each(fn (PriceBookVersion $version) => $version->loadMissing(['prices.variant', 'rules']));
        $issued = now()->toImmutable();
        $leaseLimit = $issued->addHours(72);
        $expires = $device->lease_expires_at instanceof CarbonImmutable && $device->lease_expires_at->lessThan($leaseLimit) ? $device->lease_expires_at : $leaseLimit;
        $session = CashSession::query()->where('register_id', $device->register_id)->whereIn('status', [CashSessionStatus::Open, CashSessionStatus::ClosePendingSync, CashSessionStatus::CountSubmitted, CashSessionStatus::VarianceReview])->latest('id')->first();
        $locations = InventoryLocation::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->whereNull('archived_at')->where('disposition', 'sellable')->orderBy('id')->get();
        $staff = MerchantMembership::query()->with('user')->where('merchant_id', $merchant->id)->where('status', 'active')->whereHas('storeAssignments', fn ($query) => $query->where('store_id', $store->id))->orderBy('id')->get();
        $printCapability = $this->printSigner->sign([
            'kind' => 'offline_receipt_print_capability',
            'device_id' => $device->public_id,
            'register_id' => $device->register->public_id,
            'fencing_epoch' => $device->fencing_epoch,
            'allowed_origin' => (string) config('app.url'),
            'scopes' => ['receipt_print'],
            'issued_at' => $issued->toAtomString(),
            'expires_at' => $expires->toAtomString(),
            'nonce' => (string) Str::uuid(),
        ]);
        $payload = [
            'schema_version' => 1,
            'server_time' => $issued->toAtomString(),
            'tenant' => ['merchant_id' => $merchant->public_id, 'store_id' => $store->public_id, 'register_id' => $device->register->public_id, 'timezone' => $store->timezone, 'currency' => 'PKR'],
            'device' => ['id' => $device->public_id, 'credential_version' => $device->credential_version, 'fencing_epoch' => $device->fencing_epoch, 'settings_version' => $device->settings_version],
            'lease' => ['register_expires_at' => $expires->toAtomString(), 'staff_auth_max_age_seconds' => 86400, 'snapshot_max_age_seconds' => 259200],
            'cash_session' => $session === null ? null : ['id' => $session->public_id, 'state' => $session->status->value, 'business_date' => $session->business_date->toDateString(), 'opened_at' => $session->opened_at->toAtomString(), 'current_custodian_membership_id' => $this->membershipId($staff, $session->current_custodian_user_id)],
            'locations' => $locations->map(fn (InventoryLocation $location): array => ['id' => $location->public_id, 'code' => $location->code, 'name' => $location->name])->all(),
            'staff' => $staff->map(fn (MerchantMembership $membership): array => ['membership_id' => $membership->public_id, 'name' => $membership->user->name, 'role' => $membership->role->value, 'permissions' => array_map(fn ($permission): string => $permission->value, $this->permissions->forRole($membership->role)), 'auth_expires_at' => $issued->addHours(24)->min($expires)->toAtomString(), 'pin_changed_at' => $membership->pin_changed_at?->toAtomString()])->all(),
            'catalog' => $variants->map($this->variant(...))->all(),
            'pricing' => ['version' => $resolved['version'], 'versions' => $versions->map($this->version(...))->all()],
            'policy' => ['cash_only' => true, 'external_tenders' => false, 'loyalty_redemption' => false, 'limited_coupons' => false, 'max_pending_sales' => 10000, 'max_pending_bytes' => 104857600, 'managed_storage_bytes' => 262144000, 'minimum_free_ratio' => '0.20'],
            'printing' => ['helper_protocol' => 1, 'offline_capability' => $printCapability],
        ];
        $contentHash = $this->canonical->hash($payload);
        $snapshotVersion = 'offline-v1-'.Str::uuid7();
        $envelopeData = ['device_id' => $device->public_id, 'register_id' => $device->register->public_id, 'snapshot_version' => $snapshotVersion, 'content_hash' => $contentHash, 'fencing_epoch' => $device->fencing_epoch, 'issued_at' => $issued->toAtomString(), 'expires_at' => $expires->toAtomString()];
        $encoded = rtrim(strtr(base64_encode($this->canonical->json($envelopeData)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, (string) config('app.key'));
        $signedEnvelope = $encoded.'.'.$signature;
        $snapshot = OfflineBootstrapSnapshot::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $device->register_id, 'device_enrollment_id' => $device->id, 'schema_version' => 1, 'snapshot_version' => $snapshotVersion, 'content_hash' => $contentHash, 'payload' => $payload, 'signed_envelope' => $signedEnvelope, 'issued_at' => $issued, 'expires_at' => $expires]);

        return ['snapshot_id' => $snapshot->public_id, 'schema_version' => 1, 'snapshot_version' => $snapshotVersion, 'content_hash' => $contentHash, 'issued_at' => $issued->toAtomString(), 'expires_at' => $expires->toAtomString(), 'signed_envelope' => $signedEnvelope, 'payload' => $payload];
    }

    /** @param Collection<int, MerchantMembership> $memberships */
    private function membershipId(Collection $memberships, ?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return $memberships->firstWhere('user_id', $userId)?->public_id;
    }

    /** @return array<string, mixed> */
    private function variant(ProductVariant $variant): array
    {
        return [
            'variant_id' => $variant->public_id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name,
            'sku' => $variant->sku, 'type' => $variant->product->type->value, 'weighted' => $variant->is_weighted,
            'batch_tracking' => $variant->product->batch_tracking_enabled,
            'base_unit' => ['id' => $variant->baseUnit->public_id, 'symbol' => $variant->baseUnit->symbol],
            'barcodes' => $variant->barcodes->map(fn (ProductBarcode $barcode): array => ['code' => $barcode->code, 'kind' => $barcode->kind])->all(),
            'units' => $variant->conversions->map(fn (UnitConversion $conversion): array => ['unit_id' => $conversion->fromUnit->public_id, 'factor_to_base' => $conversion->factor_to_base])->all(),
            'extras' => $variant->product->extraGroups->map($this->extraGroup(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function extraGroup(ExtraGroup $group): array
    {
        return ['name' => $group->name, 'minimum' => $group->minimum_selected, 'maximum' => $group->maximum_selected, 'options' => $group->options->where('is_active', true)->map(fn (ExtraOption $option): array => ['id' => $option->public_id, 'name' => $option->name, 'price_delta' => $option->price_delta])->values()->all()];
    }

    /** @return array<string, mixed> */
    private function version(PriceBookVersion $version): array
    {
        return [
            'id' => $version->public_id, 'scope' => $version->store_id === null ? 'merchant' : 'store', 'mode' => $version->mode->value,
            'effective_from' => $version->effective_from->toAtomString(), 'effective_until' => $version->effective_until?->toAtomString(),
            'prices' => $version->prices->map(fn (VariantPrice $price): array => ['variant_id' => $price->variant->public_id, 'customer_group' => $price->customer_group, 'unit_price' => $price->unit_price, 'tax_rate_percent' => $price->tax_rate_percent, 'tax_inclusive' => $price->tax_inclusive])->all(),
            'rules' => $version->rules->filter(fn (PromotionRule $rule): bool => $rule->maximum_redemptions === null && $rule->maximum_per_customer === null)->map(fn (PromotionRule $rule): array => ['id' => $rule->public_id, 'type' => $rule->type->value, 'name' => $rule->name, 'code' => $rule->code, 'priority' => $rule->priority, 'stackable' => $rule->stackable, 'exclusive_group' => $rule->exclusive_group, 'starts_at' => $rule->starts_at?->toAtomString(), 'ends_at' => $rule->ends_at?->toAtomString(), 'conditions' => $rule->conditions, 'reward' => $rule->reward])->values()->all(),
        ];
    }
}
