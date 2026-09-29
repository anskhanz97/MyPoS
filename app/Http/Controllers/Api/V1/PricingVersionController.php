<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Pricing\PricingVersionResolver;
use App\Domain\Pricing\PublishPricingVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublishPricingVersionRequest;
use App\Http\Resources\PricingVersionResource;
use App\Models\Merchant;
use App\Models\PriceBookVersion;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingVersionController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, PricingVersionResolver $resolver): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        $current = $resolver->current($merchant, $store);
        $history = PriceBookVersion::query()->where('merchant_id', $merchant->id)->where(fn ($query) => $query->whereNull('store_id')->orWhere('store_id', $store->id))->with(['prices.variant.product', 'rules'])->orderByDesc('published_at')->limit(100)->get();

        return response()->json(['data' => ['current_version' => $current['version'], 'versions' => PricingVersionResource::collection($history)->resolve($request)]]);
    }

    public function store(PublishPricingVersionRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, PublishPricingVersion $publisher): PricingVersionResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::PricingPublish, $store);
        $version = $publisher->handle($user, $merchant, $store, $request->validated());

        return new PricingVersionResource($version);
    }
}
