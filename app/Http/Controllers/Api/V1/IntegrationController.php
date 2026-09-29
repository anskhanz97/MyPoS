<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Integrations\IntegrationCatalog;
use App\Domain\Integrations\IntegrationManager;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantIntegration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request, Merchant $merchant, TenantAccess $access, IntegrationCatalog $catalog, IntegrationManager $manager): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        $saved = MerchantIntegration::query()->where('merchant_id', $merchant->id)->get()->keyBy('provider');
        $data = collect($catalog->all())->map(function (array $definition, string $provider) use ($saved, $manager): array {
            $integration = $saved->get($provider);

            return $integration instanceof MerchantIntegration ? $manager->safe($integration) : ['id' => null, 'provider' => $provider, 'label' => $definition['label'], 'category' => $definition['category'], 'description' => $definition['description'], 'mode' => $definition['category'] === 'payment' && $provider !== 'stripe' ? 'manual' : 'sandbox', 'state' => 'draft', 'config' => [], 'configured_credentials' => [], 'version' => null, 'last_test' => null];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Merchant $merchant, string $provider, TenantAccess $access, IntegrationCatalog $catalog, IntegrationManager $manager): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        $data = $request->validate($catalog->rules($provider));

        return response()->json(['data' => $manager->safe($manager->save($merchant, $provider, $data))]);
    }

    public function test(Request $request, Merchant $merchant, MerchantIntegration $merchantIntegration, TenantAccess $access, IntegrationManager $manager): JsonResponse
    {
        $access->ownerOrForbidden($this->user($request), $merchant);
        abort_unless($merchantIntegration->merchant_id === $merchant->id, 404);

        return response()->json(['data' => $manager->test($merchantIntegration)]);
    }

    public function active(Request $request, Merchant $merchant, TenantAccess $access, IntegrationManager $manager): JsonResponse
    {
        $access->membershipOrNotFound($this->user($request), $merchant);
        $data = MerchantIntegration::query()->where('merchant_id', $merchant->id)->where('state', 'active')->whereIn('provider', ['google_drive', 'microsoft_onedrive', 'bank_transfer', 'raast', 'easypaisa', 'jazzcash'])->get()->map(fn (MerchantIntegration $integration): array => $manager->safe($integration));

        return response()->json(['data' => $data]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
