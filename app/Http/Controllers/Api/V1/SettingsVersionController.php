<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Devices\Actions\PublishSettings;
use App\Domain\Tenancy\TenantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublishSettingsRequest;
use App\Http\Resources\SettingsVersionResource;
use App\Models\Merchant;
use App\Models\SettingsVersion;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingsVersionController extends Controller
{
    public function index(Request $request, Merchant $merchant, TenantAccess $access, ?Store $store = null): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);
        if ($store !== null) {
            abort_unless($store->merchant_id === $merchant->id, 404);
        }

        return SettingsVersionResource::collection(SettingsVersion::query()->where('merchant_id', $merchant->id)->where('store_id', $store?->id)->with('store')->orderByDesc('version')->get());
    }

    public function store(PublishSettingsRequest $request, Merchant $merchant, TenantAccess $access, PublishSettings $publish, ?Store $store = null): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access->ownerOrForbidden($user, $merchant);
        $settings = $publish->handle($user, $merchant, $store, $request->integer('expected_version'), $request->array('payload'), $request->input('effective_at'));

        return (new SettingsVersionResource($settings->load('store')))->response()->setStatusCode(201);
    }
}
