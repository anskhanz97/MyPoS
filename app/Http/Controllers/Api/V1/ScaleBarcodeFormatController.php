<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScaleBarcodeFormatRequest;
use App\Models\Merchant;
use App\Models\ScaleBarcodeFormat;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScaleBarcodeFormatController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        $formats = ScaleBarcodeFormat::query()->where('merchant_id', $merchant->id)->where(fn ($query) => $query->whereNull('store_id')->orWhere('store_id', $store->id))->orderBy('name')->get();

        return response()->json(['data' => $formats->map(fn (ScaleBarcodeFormat $format): array => $this->serialize($format))->values()]);
    }

    public function store(StoreScaleBarcodeFormatRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);
        $data = $request->validated();
        $totalLength = (int) $data['total_length'];
        abort_if((int) $data['item_start'] + (int) $data['item_length'] > $totalLength || (int) $data['value_start'] + (int) $data['value_length'] > $totalLength, 422, 'Scale barcode fields must fit inside the total label length.');
        abort_if($data['checksum'] === 'ean13' && $totalLength !== 13, 422, 'EAN-13 checksum requires a total length of 13.');
        $scopeStoreId = $data['scope'] === 'store' ? $store->id : null;
        abort_if(ScaleBarcodeFormat::query()->where('merchant_id', $merchant->id)->where('store_id', $scopeStoreId)->where('prefix', $data['prefix'])->exists(), 422, 'The scale prefix already exists in this scope.');
        $format = ScaleBarcodeFormat::query()->create([...$data, 'merchant_id' => $merchant->id, 'store_id' => $scopeStoreId, 'is_active' => true]);

        return response()->json(['data' => $this->serialize($format)], 201);
    }

    /** @return array<string, mixed> */
    private function serialize(ScaleBarcodeFormat $format): array
    {
        return ['id' => $format->public_id, 'scope' => $format->store_id === null ? 'merchant' : 'store', 'name' => $format->name, 'prefix' => $format->prefix, 'total_length' => $format->total_length, 'item_start' => $format->item_start, 'item_length' => $format->item_length, 'value_start' => $format->value_start, 'value_length' => $format->value_length, 'value_type' => $format->value_type->value, 'value_decimal_places' => $format->value_decimal_places, 'checksum' => $format->checksum, 'is_active' => $format->is_active];
    }
}
