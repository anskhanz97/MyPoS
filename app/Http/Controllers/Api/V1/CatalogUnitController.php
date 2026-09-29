<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitOfMeasureRequest;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogUnitController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);

        return response()->json(['data' => UnitOfMeasure::query()->where('merchant_id', $merchant->id)->where('is_active', true)->orderBy('name')->get()->map(fn (UnitOfMeasure $unit): array => $this->serialize($unit))->values()]);
    }

    public function store(StoreUnitOfMeasureRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);
        $code = $request->string('code')->trim()->upper()->toString();
        abort_if(UnitOfMeasure::query()->where('merchant_id', $merchant->id)->where('code', $code)->exists(), 422, 'The unit code already exists.');
        $unit = UnitOfMeasure::query()->create(['merchant_id' => $merchant->id, 'code' => $code, 'name' => $request->string('name')->trim()->toString(), 'symbol' => $request->string('symbol')->trim()->toString(), 'decimal_places' => $request->integer('decimal_places')]);

        return response()->json(['data' => $this->serialize($unit)], 201);
    }

    /** @return array<string, mixed> */
    private function serialize(UnitOfMeasure $unit): array
    {
        return ['id' => $unit->public_id, 'code' => $unit->code, 'name' => $unit->name, 'symbol' => $unit->symbol, 'decimal_places' => $unit->decimal_places];
    }
}
