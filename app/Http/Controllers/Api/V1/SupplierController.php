<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\Merchant;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'include_archived' => ['nullable', 'boolean'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Supplier::query()->where('merchant_id', $merchant->id);
        if (! $request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }
        $query->when(! empty($data['search']), fn ($searchQuery) => $searchQuery->where(fn ($scope) => $scope->where('name', 'like', '%'.$data['search'].'%')->orWhere('code', 'like', '%'.$data['search'].'%')));
        $paginator = $query->orderBy('name')->paginate((int) ($data['page_size'] ?? 25))->withQueryString();

        return response()->json(['data' => $paginator->getCollection()->map(fn (Supplier $supplier): array => $this->serialize($supplier)), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function store(StoreSupplierRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $data = $request->validated();
        $data['code'] = mb_strtoupper((string) $data['code']);
        $this->assertUniqueCode($merchant, $data['code']);
        $supplier = Supplier::query()->create(['merchant_id' => $merchant->id] + $data);
        $this->audit($merchant, $store, $user, $supplier, 'purchasing.supplier.created');

        return response()->json(['data' => $this->serialize($supplier)], 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, Supplier $supplier, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($supplier, $merchant);

        return response()->json(['data' => $this->serialize($supplier)]);
    }

    public function update(StoreSupplierRequest $request, Merchant $merchant, Store $store, Supplier $supplier, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($supplier, $merchant);
        abort_if($supplier->archived_at !== null, 409, 'An archived supplier cannot be edited.');
        $data = $request->validated();
        $data['code'] = mb_strtoupper((string) $data['code']);
        $this->assertUniqueCode($merchant, $data['code'], $supplier);
        $supplier->update($data);
        $this->audit($merchant, $store, $user, $supplier, 'purchasing.supplier.updated');

        return response()->json(['data' => $this->serialize($supplier->refresh())]);
    }

    public function destroy(Request $request, Merchant $merchant, Store $store, Supplier $supplier, RoleAuthorizer $authorizer): JsonResponse
    {
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::PurchasingManage, $store);
        $this->assertScope($supplier, $merchant);
        if ($supplier->archived_at === null) {
            $supplier->update(['archived_at' => now()]);
            $this->audit($merchant, $store, $user, $supplier, 'purchasing.supplier.archived');
        }

        return response()->json(['data' => $this->serialize($supplier->refresh())]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function assertScope(Supplier $supplier, Merchant $merchant): void
    {
        abort_unless($supplier->merchant_id === $merchant->id, 404);
    }

    private function assertUniqueCode(Merchant $merchant, string $code, ?Supplier $except = null): void
    {
        $exists = Supplier::query()->where('merchant_id', $merchant->id)->where('code', $code)->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => 'The supplier code is already in use for this merchant.']);
        }
    }

    private function audit(Merchant $merchant, Store $store, User $user, Supplier $supplier, string $event): void
    {
        SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id, 'event_code' => $event, 'outcome' => 'succeeded', 'metadata' => ['supplier_id' => $supplier->public_id, 'supplier_code' => $supplier->code], 'occurred_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function serialize(Supplier $supplier): array
    {
        return ['id' => $supplier->public_id, 'code' => $supplier->code, 'name' => $supplier->name, 'contact_name' => $supplier->contact_name, 'phone' => $supplier->phone, 'email' => $supplier->email, 'tax_reference' => $supplier->tax_reference, 'address_line_1' => $supplier->address_line_1, 'address_line_2' => $supplier->address_line_2, 'city' => $supplier->city, 'notes' => $supplier->notes, 'archived_at' => $supplier->archived_at?->toAtomString()];
    }
}
