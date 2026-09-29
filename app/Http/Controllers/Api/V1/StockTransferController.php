<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Inventory\CreateStockTransfer;
use App\Domain\Inventory\DispatchStockTransfer;
use App\Domain\Inventory\Enums\StockTransferState;
use App\Domain\Inventory\Enums\TransferReceiptResolution;
use App\Domain\Inventory\ReceiveStockTransfer;
use App\Domain\Shared\Math\Decimal;
use App\Http\Controllers\Controller;
use App\Http\Requests\DispatchStockTransferRequest;
use App\Http\Requests\ReceiveStockTransferRequest;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StockTransferController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryView, $store);
        $data = $request->validate(['state' => ['nullable', 'in:draft,dispatched,partially_received,received,cancelled'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = StockTransfer::query()->where('merchant_id', $merchant->id)->where(fn ($scope) => $scope->where('source_store_id', $store->id)->orWhere('destination_store_id', $store->id))->with(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']);
        $query->when(! empty($data['state']), fn ($stateQuery) => $stateQuery->where('state', $data['state']));
        $paginator = $query->orderByDesc('id')->paginate((int) ($data['page_size'] ?? 25))->withQueryString();

        return response()->json(['data' => $paginator->getCollection()->map(fn (StockTransfer $transfer): array => $this->serialize($transfer)), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function store(StoreStockTransferRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CreateStockTransfer $creator): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        $data = $request->validated();
        $destination = (new Store)->resolveRouteBinding((string) $data['destination_store_id']);
        $sourceLocation = (new InventoryLocation)->resolveRouteBinding((string) $data['source_location_id']);
        $destinationLocation = (new InventoryLocation)->resolveRouteBinding((string) $data['destination_location_id']);
        abort_unless($destination instanceof Store && $sourceLocation instanceof InventoryLocation && $destinationLocation instanceof InventoryLocation, 422, 'A transfer scope reference is invalid.');
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $destination);
        $transfer = $creator->handle($merchant, $store, $destination, $sourceLocation, $destinationLocation, $user, $data['notes'] ?? null, $data['lines']);

        return response()->json(['data' => $this->serialize($transfer)], 201);
    }

    public function dispatch(DispatchStockTransferRequest $request, Merchant $merchant, Store $store, StockTransfer $transfer, RoleAuthorizer $authorizer, DispatchStockTransfer $dispatcher): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        abort_unless($transfer->merchant_id === $merchant->id && $transfer->source_store_id === $store->id, 404);
        $result = $dispatcher->handle($transfer, $user, $request->string('idempotency_key')->toString(), $request->integer('expected_version'));

        return response()->json(['data' => $this->serialize($result['transfer']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function receive(ReceiveStockTransferRequest $request, Merchant $merchant, Store $store, StockTransfer $transfer, RoleAuthorizer $authorizer, ReceiveStockTransfer $receiver): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($transfer->merchant_id === $merchant->id && $transfer->source_store_id === $store->id, 404);
        $destination = Store::query()->where('merchant_id', $merchant->id)->findOrFail($transfer->destination_store_id);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $destination);
        $data = $request->validated();
        $result = $receiver->handle($transfer, $user, (string) $data['idempotency_key'], (int) $data['expected_version'], TransferReceiptResolution::from((string) $data['resolution']), $data['reason'] ?? null, $data['lines']);

        return response()->json(['data' => ['transfer' => $this->serialize($result['transfer']), 'receipt_id' => $result['receipt']->public_id], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function cancel(Request $request, Merchant $merchant, Store $store, StockTransfer $transfer, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::InventoryManage, $store);
        abort_unless($transfer->merchant_id === $merchant->id && $transfer->source_store_id === $store->id, 404);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $cancelled = DB::transaction(function () use ($transfer, $user, $data): StockTransfer {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->state !== StockTransferState::Draft || $locked->version !== (int) $data['expected_version']) {
                throw new ConflictHttpException('Only the current draft transfer can be cancelled.');
            }
            $locked->update(['state' => StockTransferState::Cancelled, 'version' => $locked->version + 1, 'cancelled_at' => now()]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->source_store_id, 'actor_user_id' => $user->id, 'event_code' => 'inventory.transfer.cancelled', 'outcome' => 'succeeded', 'metadata' => ['transfer_id' => $locked->public_id, 'reason' => $data['reason']], 'occurred_at' => now()]);

            return $locked->load(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']);
        });

        return response()->json(['data' => $this->serialize($cancelled)]);
    }

    /** @return array<string, mixed> */
    private function serialize(StockTransfer $transfer): array
    {
        $transfer->loadMissing(['sourceStore', 'destinationStore', 'sourceLocation', 'destinationLocation', 'lines.sourceVariant.product', 'lines.destinationVariant.product', 'lines.sourceBatch']);

        return [
            'id' => $transfer->public_id, 'number' => $transfer->number, 'state' => $transfer->state->value, 'version' => $transfer->version,
            'source_store' => ['id' => $transfer->sourceStore->public_id, 'name' => $transfer->sourceStore->name], 'destination_store' => ['id' => $transfer->destinationStore->public_id, 'name' => $transfer->destinationStore->name],
            'source_location' => ['id' => $transfer->sourceLocation->public_id, 'code' => $transfer->sourceLocation->code], 'destination_location' => ['id' => $transfer->destinationLocation->public_id, 'code' => $transfer->destinationLocation->code],
            'notes' => $transfer->notes, 'dispatched_at' => $transfer->dispatched_at?->toAtomString(), 'received_at' => $transfer->received_at?->toAtomString(),
            'lines' => $transfer->lines->map(fn ($line): array => [
                'id' => $line->public_id, 'source_variant_id' => $line->sourceVariant->public_id, 'source_sku' => $line->sourceVariant->sku,
                'destination_variant_id' => $line->destinationVariant->public_id, 'destination_sku' => $line->destinationVariant->sku,
                'batch' => $line->sourceBatch === null ? null : ['id' => $line->sourceBatch->public_id, 'code' => $line->sourceBatch->code, 'expiry_date' => $line->sourceBatch->expiry_date->toDateString()],
                'factor_to_destination_base' => $line->factor_to_destination_base, 'requested_source_quantity_base' => $line->requested_source_quantity_base,
                'expected_destination_quantity_base' => $line->expected_destination_quantity_base, 'received_destination_quantity_base' => $line->received_destination_quantity_base,
                'in_transit_destination_quantity_base' => $this->inTransit($line->expected_destination_quantity_base, $line->received_destination_quantity_base, $line->short_destination_quantity_base),
                'short_destination_quantity_base' => $line->short_destination_quantity_base, 'excess_destination_quantity_base' => $line->excess_destination_quantity_base,
                'source_unit_cost' => $line->source_unit_cost, 'destination_unit_cost' => $line->destination_unit_cost,
            ])->all(),
        ];
    }

    private function inTransit(string $expected, string $received, string $short): string
    {
        $difference = BigDecimal::of($expected)->minus($received)->minus($short);

        return Decimal::fixed($difference->isNegative() ? BigDecimal::zero() : $difference, 6);
    }
}
