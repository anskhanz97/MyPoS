<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Customers\CustomerScope;
use App\Domain\Customers\CustomerSnapshotBuilder;
use App\Domain\Customers\CustomerWriter;
use App\Domain\Customers\PakistanPhoneNormalizer;
use App\Domain\Tenancy\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\CustomerGroup;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CustomerScope $scope, PakistanPhoneNormalizer $phones): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $this->minimal($request, $membership->role);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:active,archived,all'], 'group_id' => ['nullable', 'uuid'], 'page_size' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = OperationalCustomer::query()->where('merchant_id', $merchant->id)->where('store_id', $scope->storeId($merchant, $store))->with(['customerGroup', 'loyaltyLink.loyaltyMember', 'store']);
        if (($validated['status'] ?? 'active') !== 'all') {
            ($validated['status'] ?? 'active') === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        if (! empty($validated['group_id'])) {
            $group = (new CustomerGroup)->resolveRouteBinding((string) $validated['group_id']);
            abort_unless($group instanceof CustomerGroup && $group->merchant_id === $merchant->id && $group->store_id === $scope->storeId($merchant, $store), 422, 'The customer group is outside this customer scope.');
            $query->where('customer_group_id', $group->id);
        }
        if (! empty($validated['q'])) {
            $rawTerm = (string) $validated['q'];
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($rawTerm)).'%';
            $phone = null;
            if (preg_match('/^[+0-9().\s-]+$/', $rawTerm) === 1) {
                try {
                    $phone = $phones->normalize($rawTerm);
                } catch (ValidationException) {
                    // Partial numeric input remains a bounded text search.
                }
            }
            $query->where(fn ($nested) => $nested->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(local_reference) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$term])
                ->when($phone !== null, fn ($phoneQuery) => $phoneQuery->orWhere('normalized_phone', $phone)));
        }

        return CustomerResource::collection($query->orderBy('name')->paginate((int) ($validated['page_size'] ?? 25))->withQueryString());
    }

    public function store(StoreCustomerRequest $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CustomerWriter $writer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $this->guardCashierWrite($membership->role, $request->validated(), ['local_reference', 'name', 'phone', 'email', 'email_receipt_preference']);
        $this->minimal($request, $membership->role);

        return response()->json(['data' => (new CustomerResource($writer->create($user, $merchant, $store, $request->validated())))->resolve($request)], 201);
    }

    public function show(Request $request, Merchant $merchant, Store $store, OperationalCustomer $customer, RoleAuthorizer $authorizer, CustomerScope $scope): CustomerResource
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $this->minimal($request, $membership->role);
        abort_unless($customer->merchant_id === $merchant->id && $customer->store_id === $scope->storeId($merchant, $store), 404);

        return new CustomerResource($customer->load(['customerGroup', 'loyaltyLink.loyaltyMember', 'store']));
    }

    public function update(UpdateCustomerRequest $request, Merchant $merchant, Store $store, OperationalCustomer $customer, RoleAuthorizer $authorizer, CustomerWriter $writer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $this->guardCashierWrite($membership->role, $request->validated(), ['expected_version', 'name', 'phone', 'email', 'email_receipt_preference']);
        $this->minimal($request, $membership->role);

        return response()->json(['data' => (new CustomerResource($writer->update($user, $merchant, $store, $customer, $request->validated())))->resolve($request)]);
    }

    public function duplicates(Request $request, Merchant $merchant, Store $store, RoleAuthorizer $authorizer, CustomerScope $scope, PakistanPhoneNormalizer $phones): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $authorizer->authorize($user, $merchant, Permission::CustomerManage, $store);
        $this->minimal($request, $membership->role);
        $validated = $request->validate(['phone' => ['nullable', 'string', 'max:32', 'required_without:email'], 'email' => ['nullable', 'email:rfc', 'max:254', 'required_without:phone']]);
        $phone = $phones->normalize(isset($validated['phone']) ? (string) $validated['phone'] : null);
        $email = isset($validated['email']) ? mb_strtolower((string) $validated['email']) : null;
        $matches = OperationalCustomer::query()->where('merchant_id', $merchant->id)->where('store_id', $scope->storeId($merchant, $store))->where(fn ($query) => $query->when($phone !== null, fn ($q) => $q->orWhere('normalized_phone', $phone))->when($email !== null, fn ($q) => $q->orWhere('normalized_email', $email)))->with(['customerGroup', 'loyaltyLink.loyaltyMember', 'store'])->limit(25)->get();

        return CustomerResource::collection($matches);
    }

    public function saleAssociation(Request $request, Merchant $merchant, Store $store, OperationalCustomer $customer, RoleAuthorizer $authorizer, CustomerScope $scope, CustomerSnapshotBuilder $snapshots): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::SaleCreate, $store);
        abort_unless($customer->merchant_id === $merchant->id && $customer->store_id === $scope->storeId($merchant, $store) && $customer->archived_at === null, 404);

        return response()->json(['data' => $snapshots->build($customer)]);
    }

    private function minimal(Request $request, MembershipRole $role): void
    {
        $request->attributes->set('customer_minimal', $role === MembershipRole::Cashier);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private function guardCashierWrite(MembershipRole $role, array $data, array $allowed): void
    {
        if ($role === MembershipRole::Cashier) {
            abort_if(array_diff(array_keys($data), $allowed) !== [], 403, 'Cashiers may edit only checkout contact fields.');
        }
    }
}
