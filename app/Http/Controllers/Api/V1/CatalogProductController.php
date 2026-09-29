<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Catalog\CatalogProductWriter;
use App\Domain\Catalog\CatalogScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplaceBundleComponentsRequest;
use App\Http\Requests\StoreCatalogProductRequest;
use App\Http\Requests\UpdateCatalogProductRequest;
use App\Http\Resources\CatalogProductResource;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogProductController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CatalogScope $scope, CatalogProductWriter $writer): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'type' => ['nullable', 'in:standard,weighted,bundle'], 'status' => ['nullable', 'in:active,archived,all'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $scopeStoreId = $scope->storeId($merchant, $store);
        $query = Product::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->with($writer->relations());
        if ($scopeStoreId === null) {
            $query->whereHas('storeAvailabilities', fn ($availability) => $availability->where('store_id', $store->id)->where('is_available', true));
        }
        if (($validated['status'] ?? 'active') !== 'all') {
            ($validated['status'] ?? 'active') === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
            $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', $term)->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('code', 'like', $term))));
        }

        return CatalogProductResource::collection($query->orderBy('name')->paginate((int) ($validated['page_size'] ?? 25))->withQueryString());
    }

    public function store(StoreCatalogProductRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CatalogProductWriter $writer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);

        return (new CatalogProductResource($writer->create($user, $merchant, $store, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Product $product, RoleAuthorizer $authorizer, CatalogScope $scope, CatalogProductWriter $writer): CatalogProductResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $scope->storeId($merchant, $store), 404);

        return new CatalogProductResource($product->load($writer->relations()));
    }

    public function update(UpdateCatalogProductRequest $request, Merchant $merchant, Store $store, Product $product, RoleAuthorizer $authorizer, CatalogProductWriter $writer): CatalogProductResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);

        return new CatalogProductResource($writer->update($user, $merchant, $store, $product, $request->validated()));
    }

    public function replaceBundle(ReplaceBundleComponentsRequest $request, Merchant $merchant, Store $store, ProductVariant $variant, RoleAuthorizer $authorizer, CatalogProductWriter $writer): CatalogProductResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);

        return new CatalogProductResource($writer->replaceBundle($user, $merchant, $store, $variant, $request->integer('expected_structure_version'), $request->array('components')));
    }
}
