<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\CatalogScope;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Models\FiscalConfiguration;
use App\Models\FiscalDocument;
use App\Models\FiscalProductMapping;
use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FiscalController extends Controller
{
    public function show(Request $request, Merchant $merchant, Store $store, TenantAccess $access): JsonResponse
    {
        $access->assignedStoreOrNotFound($this->user($request), $merchant, $store);
        $config = FiscalConfiguration::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->first();
        $documents = FiscalDocument::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->with('attempts')->latest()->limit(100)->get();

        return response()->json(['data' => ['configuration' => $config ? $this->configuration($config) : null, 'documents' => $documents, 'unsupported_authorities' => ['pra' => 'Current API/outage contract unavailable', 'srb' => 'Onboarding contract and sandbox not proven', 'kpra' => 'Official field shapes conflict', 'bra' => 'Current integration API unavailable', 'ict' => 'Applicability/technical route unverified']]]);
    }

    public function configure(Request $request, Merchant $merchant, Store $store, TenantAccess $access): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        abort_unless($store->merchant_id === $merchant->id, 404);
        $d = $request->validate(['authority' => ['required', Rule::in(['fbr_di', 'pra', 'srb', 'kpra', 'bra', 'ict'])], 'environment' => ['required', Rule::in(['sandbox', 'production'])], 'applicability' => ['required', Rule::in(['not_required', 'required', 'pending_verification'])], 'state' => ['required', Rule::in(['draft', 'blocked', 'active'])], 'tax_registration_number' => ['nullable', 'string', 'max:80'], 'outlet_reference' => ['nullable', 'string', 'max:120'], 'outage_policy' => ['required', Rule::in(['block_sale', 'local_pending', 'authority_middleware_pending', 'manual_contingency'])], 'profile' => ['nullable', 'array'], 'bearer_token' => ['nullable', 'string', 'max:1000'], 'evidence_hash' => ['nullable', 'regex:/^[0-9a-f]{64}$/'], 'sandbox_verified_at' => ['nullable', 'date'], 'expected_version' => ['required', 'integer', 'min:0']]);
        if ($d['authority'] !== 'fbr_di') {
            abort_unless($d['state'] === 'blocked', 422, 'This authority remains blocked until a canonical current contract and sandbox evidence are supplied.');
        }
        if ($d['state'] === 'active') {
            abort_unless($d['authority'] === 'fbr_di' && $d['environment'] === 'sandbox', 422, 'Only the verified FBR DI v1.12 sandbox profile can be activated in this build.');
            foreach (['tax_registration_number', 'outlet_reference', 'evidence_hash', 'sandbox_verified_at'] as $field) {
                abort_if(empty($d[$field]), 422, "{$field} is required for sandbox activation.");
            }abort_unless($d['outage_policy'] === 'block_sale', 422, 'FBR outage selling is blocked until written authority/integrator procedure exists.');
            abort_if(empty($d['bearer_token']), 422, 'A sandbox bearer token is required.');
        }
        $existing = FiscalConfiguration::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)->where('authority', $d['authority'])->first();
        $currentVersion = $existing instanceof FiscalConfiguration ? $existing->version : 0;
        abort_unless($currentVersion === (int) $d['expected_version'], 409, 'Fiscal configuration changed. Reload before saving.');
        $credentials = $existing?->credentialData() ?? [];
        if (! empty($d['bearer_token'])) {
            $credentials = ['bearer_token' => $d['bearer_token']];
        }
        $values = ['adapter_version' => $d['authority'] === 'fbr_di' ? 'fbr-di-v1.12' : 'unsupported', 'environment' => $d['environment'], 'applicability' => $d['applicability'], 'state' => $d['state'], 'tax_registration_number' => $d['tax_registration_number'] ?? null, 'outlet_reference' => $d['outlet_reference'] ?? null, 'outage_policy' => $d['outage_policy'], 'profile' => $d['profile'] ?? null, 'credentials' => $credentials ?: null, 'evidence_hash' => $d['evidence_hash'] ?? null, 'sandbox_verified_at' => $d['sandbox_verified_at'] ?? null, 'version' => $currentVersion + 1];
        $config = FiscalConfiguration::query()->updateOrCreate(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'authority' => $d['authority']], $values);

        return response()->json(['data' => $this->configuration($config)], $existing ? 200 : 201);
    }

    public function mapping(Request $request, Merchant $merchant, Store $store, FiscalConfiguration $fiscalConfiguration, TenantAccess $access, CatalogScope $scope): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        abort_unless($fiscalConfiguration->merchant_id === $merchant->id && $fiscalConfiguration->store_id === $store->id, 404);
        $d = $request->validate(['variant_id' => ['required', 'string'], 'hs_code' => ['required', 'string', 'max:30'], 'uom_code' => ['required', 'string', 'max:80'], 'rate_label' => ['required', 'string', 'max:80'], 'sale_type' => ['required', 'string', 'max:120'], 'sro_schedule_no' => ['nullable', 'string', 'max:80'], 'sro_item_serial_no' => ['nullable', 'string', 'max:80'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $variant = (new ProductVariant)->resolveRouteBinding($d['variant_id']);
        abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $merchant->id && $variant->store_id === $scope->storeId($merchant, $store), 404);
        $mapping = FiscalProductMapping::query()->create(['fiscal_configuration_id' => $fiscalConfiguration->id, 'product_variant_id' => $variant->id] + array_diff_key($d, ['variant_id' => true]));

        return response()->json(['data' => $mapping], 201);
    }

    public function consume(Request $request, Merchant $merchant, TenantAccess $access, FiscalService $service): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);

        return response()->json(['data' => ['consumed' => $service->consumeOutbox(100)]], 202);
    }

    public function retry(Request $request, Merchant $merchant, FiscalDocument $fiscalDocument, TenantAccess $access, FiscalService $service): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        abort_unless($fiscalDocument->merchant_id === $merchant->id, 404);

        return response()->json(['data' => $service->retry($fiscalDocument)], 202);
    }

    public function reconcile(Request $request, Merchant $merchant, FiscalDocument $fiscalDocument, TenantAccess $access, FiscalService $service): JsonResponse
    {
        $user = $this->user($request);
        $access->ownerOrForbidden($user, $merchant);
        abort_unless($fiscalDocument->merchant_id === $merchant->id, 404);
        $d = $request->validate(['state' => ['required', Rule::in(['accepted', 'rejected'])], 'authority_reference' => ['required_if:state,accepted', 'nullable', 'string', 'max:160'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);

        return response()->json(['data' => $service->reconcile($fiscalDocument, $user, $d['state'], $d['authority_reference'] ?? '', $d['reason'])]);
    }

    /** @return array<string,mixed> */
    private function configuration(FiscalConfiguration $c): array
    {
        return ['id' => $c->public_id, 'authority' => $c->authority, 'adapter_version' => $c->adapter_version, 'environment' => $c->environment, 'applicability' => $c->applicability, 'state' => $c->state, 'tax_registration_number' => $c->tax_registration_number, 'outlet_reference' => $c->outlet_reference, 'outage_policy' => $c->outage_policy, 'profile' => $c->profileData(), 'credential_configured' => isset($c->credentialData()['bearer_token']), 'evidence_hash' => $c->evidence_hash, 'version' => $c->version];
    }

    private function user(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }
}
