<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Catalog\BarcodeResolver;
use App\Domain\Catalog\CatalogProductWriter;
use App\Domain\Catalog\CatalogScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogProductResource;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogBarcodeController extends Controller
{
    public function __invoke(Request $request, Merchant $merchant, Store $store, BarcodeResolver $resolver, CatalogScope $scope, RoleAuthorizer $authorizer, CatalogProductWriter $writer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        $validated = $request->validate(['barcode' => ['required', 'string', 'max:64']]);
        $result = $resolver->resolve($merchant, $store, $validated['barcode'], $scope);
        $variant = $result['barcode']->variant()->with(['product' => fn ($query) => $query->with($writer->relations())])->firstOrFail();

        return response()->json(['data' => ['scan' => ['raw_barcode' => $validated['barcode'], 'kind' => $result['kind'], 'quantity' => $result['quantity'], 'embedded_price' => $result['embedded_price']], 'matched_variant_id' => $variant->public_id, 'product' => (new CatalogProductResource($variant->product))->resolve()]]);
    }
}
