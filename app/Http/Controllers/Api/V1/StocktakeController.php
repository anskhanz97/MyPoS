<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\OpenStocktake;
use App\Domain\Inventory\PostStocktake;
use App\Domain\Inventory\RecordStocktakeCounts;
use App\Domain\Inventory\ReviewStocktake;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpenStocktakeRequest;
use App\Http\Requests\PostStocktakeRequest;
use App\Http\Requests\RecordStocktakeCountsRequest;
use App\Http\Requests\ReviewStocktakeRequest;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\Stocktake;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StocktakeController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $data = $request->validate(['state' => ['nullable', 'in:counting,review,posted,cancelled'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Stocktake::query()->where('merchant_id', $merchant->id)->where('store_id', $store->id)
            ->with(['location', 'lines.variant.product', 'lines.batch']);
        $query->when(! empty($data['state']), fn ($stateQuery) => $stateQuery->where('state', $data['state']));
        $paginator = $query->orderByDesc('id')->paginate((int) ($data['page_size'] ?? 25))->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Stocktake $stocktake): array => $this->serialize($stocktake)),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Stocktake $stocktake, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $this->assertScope($stocktake, $merchant, $store);

        return response()->json(['data' => $this->serialize($stocktake)]);
    }

    public function store(OpenStocktakeRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, OpenStocktake $opener): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $location = (new InventoryLocation)->resolveRouteBinding((string) $data['location_id']);
        abort_unless($location instanceof InventoryLocation, 422, 'The stocktake location is invalid.');
        $stocktake = $opener->handle($merchant, $store, $location, $user, $data['notes'] ?? null);

        return response()->json(['data' => $this->serialize($stocktake)], 201);
    }

    public function counts(RecordStocktakeCountsRequest $request, Merchant $merchant, Store $store, Stocktake $stocktake, RoleAuthorizer $authorizer, RecordStocktakeCounts $recorder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $this->assertScope($stocktake, $merchant, $store);
        $data = $request->validated();

        return response()->json(['data' => $this->serialize($recorder->handle($stocktake, (int) $data['expected_version'], $data['lines']))]);
    }

    public function review(ReviewStocktakeRequest $request, Merchant $merchant, Store $store, Stocktake $stocktake, RoleAuthorizer $authorizer, ReviewStocktake $reviewer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $this->assertScope($stocktake, $merchant, $store);

        return response()->json(['data' => $this->serialize($reviewer->handle($stocktake, $user, $request->integer('expected_version')))]);
    }

    public function post(PostStocktakeRequest $request, Merchant $merchant, Store $store, Stocktake $stocktake, RoleAuthorizer $authorizer, PostStocktake $poster): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $this->assertScope($stocktake, $merchant, $store);
        $data = $request->validated();
        $result = $poster->handle($stocktake, $user, (string) $data['idempotency_key'], (int) $data['expected_version'], (string) $data['reason']);

        return response()->json(['data' => $this->serialize($result['stocktake']) + ['movements_posted' => $result['movements_posted']], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    private function assertScope(Stocktake $stocktake, Merchant $merchant, Store $store): void
    {
        abort_unless($stocktake->merchant_id === $merchant->id && $stocktake->store_id === $store->id, 404);
    }

    /** @return array<string, mixed> */
    private function serialize(Stocktake $stocktake): array
    {
        $stocktake->loadMissing(['location', 'lines.variant.product', 'lines.batch']);

        return [
            'id' => $stocktake->public_id, 'number' => $stocktake->number, 'state' => $stocktake->state->value, 'version' => $stocktake->version,
            'location' => ['id' => $stocktake->location->public_id, 'code' => $stocktake->location->code, 'name' => $stocktake->location->name],
            'baseline_movement_id' => $stocktake->baseline_movement_id, 'opened_at' => $stocktake->opened_at->toAtomString(), 'reviewed_at' => $stocktake->reviewed_at?->toAtomString(), 'posted_at' => $stocktake->posted_at?->toAtomString(),
            'lines' => $stocktake->lines->map(fn ($line): array => [
                'id' => $line->public_id, 'variant_id' => $line->variant->public_id, 'sku' => $line->variant->sku, 'product_name' => $line->variant->product->name,
                'batch' => $line->batch === null ? null : ['id' => $line->batch->public_id, 'code' => $line->batch->code, 'expiry_date' => $line->batch->expiry_date->toDateString()],
                'baseline_quantity_base' => $line->baseline_quantity_base, 'counted_quantity_base' => $line->counted_quantity_base,
                'count_cutoff_movement_id' => $line->count_cutoff_movement_id, 'expected_at_count_quantity_base' => $line->expected_at_count_quantity_base,
                'discrepancy_quantity_base' => $line->discrepancy_quantity_base,
            ])->all(),
        ];
    }
}
