<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Reporting\ReportSetupImport;
use App\Domain\Reporting\ReportSnapshots;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingSetupController extends Controller
{
    private const TABLES = ['categories' => 'product_categories', 'reporting-categories' => 'reporting_categories', 'brands' => 'product_brands', 'product-types' => 'product_reporting_types', 'register-groups' => 'register_groups'];

    public function index(Request $request, Merchant $merchant, RoleAuthorizer $auth): JsonResponse
    {
        [$user, $store] = $this->authorize($request, $merchant, $auth);
        $products = Product::query()->where('merchant_id', $merchant->id)->when($merchant->store_data_mode->value === 'independent', fn ($q) => $q->where('store_id', $store?->id))->with('variants')->orderBy('name');
        if ($request->filled('search')) {
            $products->where('name', 'like', '%'.$request->string('search')->toString().'%');
        }
        $data = [];
        foreach (self::TABLES as $kind => $table) {
            $data[$kind] = DB::table($table)->where('merchant_id', $merchant->id)->where(fn ($q) => $q->whereNull('store_id')->when($store !== null, fn ($q) => $q->orWhere('store_id', $store->id)))->orderBy('name')->get()->map(fn ($r) => ['id' => Uuid::fromBytes($r->public_id)->toString(), 'internal_id' => $r->id, 'name' => $r->name, 'code' => $r->code, 'classification' => $r->classification, 'archived' => $r->archived_at !== null]);
        }
        $data['suppliers'] = DB::table('suppliers')->where('merchant_id', $merchant->id)->orderBy('name')->get()->map(fn ($r) => ['id' => Uuid::fromBytes($r->public_id)->toString(), 'internal_id' => $r->id, 'name' => $r->name]);
        $data['products'] = $products->paginate(50);
        $data['registers'] = Register::query()->where('merchant_id', $merchant->id)->when($store !== null, fn ($q) => $q->where('store_id', $store->id))->get(['public_id', 'name', 'register_group_id']);
        $data['reorder_policies'] = DB::table('inventory_reorder_policies')->where('merchant_id', $merchant->id)->when($store !== null, fn ($q) => $q->where('store_id', $store->id))->get();

        return response()->json(['data' => $data]);
    }

    public function save(Request $request, Merchant $merchant, string $kind, RoleAuthorizer $auth, ?string $id = null): JsonResponse
    {
        [, $store] = $this->authorize($request, $merchant, $auth);
        $table = self::TABLES[$kind] ?? abort(404);
        $existing = $id === null ? null : $this->lookup($merchant, $table, $id, $store);
        $unique = Rule::unique($table, 'code')->where('merchant_id', $merchant->id)->where('scope_store_id', ($existing !== null ? ($existing->store_id ?? 0) : ($store !== null ? $store->id : 0)));
        if ($existing !== null) {
            $unique->ignore($existing->id);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_-]+$/', $unique], 'classification' => ['nullable', Rule::in(['wet', 'dry', 'other'])], 'archived' => ['sometimes', 'boolean']]);
        $values = ['name' => $data['name'], 'code' => $data['code'], 'classification' => $kind === 'product-types' ? ($data['classification'] ?? 'other') : null, 'archived_at' => ($data['archived'] ?? false) ? now() : null, 'updated_at' => now()];
        if ($existing === null) {
            DB::table($table)->insert($values + ['public_id' => Str::uuid7()->getBytes(), 'merchant_id' => $merchant->id, 'store_id' => $store?->id, 'created_at' => now()]);
        } else {
            DB::table($table)->where('id', $existing->id)->update($values);
        }

        return response()->json(['data' => ['saved' => true]], 201);
    }

    public function export(Request $request, Merchant $merchant, RoleAuthorizer $auth): StreamedResponse
    {
        [, $store] = $this->authorize($request, $merchant, $auth);
        abort_unless($store !== null, 422, 'Choose a store to export reporting classifications and reorder levels.');
        $query = ProductVariant::query()->where('merchant_id', $merchant->id)->where('store_id', $merchant->store_data_mode->value === 'independent' ? $store->id : null)->with('product')->orderBy('id');
        abort_if((clone $query)->count() > 1000000, 422, 'This export exceeds one million variants.');
        $codes = [];
        foreach (ReportSnapshots::DIMENSIONS as $column => $table) {
            $codes[$column] = DB::table($table)->where('merchant_id', $merchant->id)->get()->mapWithKeys(fn ($row) => [$row->id => $table === 'suppliers' ? Uuid::fromBytes($row->public_id)->toString() : $row->code])->all();
        }
        $policies = DB::table('inventory_reorder_policies')->where('merchant_id', $merchant->id)->where('store_id', $store->id)->get()->keyBy('product_variant_id');

        return response()->streamDownload(function () use ($query, $codes, $policies): void {
            $out = fopen('php://output', 'wb');
            abort_unless(is_resource($out), 500);
            fputcsv($out, ReportSetupImport::HEADERS, escape: '');
            foreach ($query->lazyById(500) as $variant) {
                $product = $variant->product;
                $row = [$variant->sku];
                foreach (array_keys(ReportSnapshots::DIMENSIONS) as $column) {
                    $row[] = $codes[$column][$product->getAttribute($column)] ?? '';
                }
                $row[] = $variant->attributes['size'] ?? '';
                $row[] = $product->getAttribute('is_miscellaneous') ? 'yes' : 'no';
                $policy = $policies->get($variant->id);
                $row[] = $policy !== null ? $policy->minimum_quantity : '';
                $row[] = $policy !== null ? ($policy->target_quantity ?? '') : '';
                fputcsv($out, array_map(ReportSetupImport::encodeCsvCell(...), $row), escape: '');
            }
            fclose($out);
        }, 'reporting-catalog.csv', ['Cache-Control' => 'private, no-store', 'Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function product(Request $request, Merchant $merchant, Product $product, RoleAuthorizer $auth): JsonResponse
    {
        [, $store] = $this->authorize($request, $merchant, $auth);
        abort_unless($product->merchant_id === $merchant->id && ($product->store_id === null || $product->store_id === $store?->id), 404);
        $rules = ['is_miscellaneous' => ['sometimes', 'boolean']];
        foreach (ReportSnapshots::DIMENSIONS as $column => $table) {
            $rules[$column] = ['nullable', 'uuid'];
        }
        $data = $request->validate($rules);
        foreach (ReportSnapshots::DIMENSIONS as $column => $table) {
            if (array_key_exists($column, $data)) {
                $row = $data[$column] === null ? null : $this->lookup($merchant, $table, $data[$column], $store);
                abort_if($table !== 'suppliers' && $row !== null && $row->archived_at !== null && $row->id !== $product->getAttribute($column), 422, 'Archived classifications cannot be assigned to another product.');
                $data[$column] = $row?->id;
            }
        }
        $product->forceFill($data)->save();

        return response()->json(['data' => ['saved' => true]]);
    }

    public function variant(Request $request, Merchant $merchant, ProductVariant $variant, RoleAuthorizer $auth): JsonResponse
    {
        [, $store] = $this->authorize($request, $merchant, $auth);
        abort_unless($variant->merchant_id === $merchant->id && ($variant->store_id === null || $variant->store_id === $store?->id), 404);
        $data = $request->validate(['size' => ['nullable', 'string', 'max:80'], 'minimum_quantity' => ['nullable', 'numeric', 'min:0'], 'target_quantity' => ['nullable', 'numeric', 'gte:minimum_quantity']]);
        if (array_key_exists('size', $data)) {
            $variant->forceFill(['attributes' => array_replace($variant->attributes ?? [], ['size' => $data['size']])])->save();
        }
        if (isset($data['minimum_quantity'])) {
            abort_unless($store !== null, 422, 'Choose a store for a reorder policy.');
            DB::table('inventory_reorder_policies')->updateOrInsert(['store_id' => $store->id, 'product_variant_id' => $variant->id], ['merchant_id' => $merchant->id, 'minimum_quantity' => $data['minimum_quantity'], 'target_quantity' => $data['target_quantity'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['data' => ['saved' => true]]);
    }

    public function register(Request $request, Merchant $merchant, Register $register, RoleAuthorizer $auth): JsonResponse
    {
        [, $store] = $this->authorize($request, $merchant, $auth);
        abort_unless($register->merchant_id === $merchant->id && ($store === null || $register->store_id === $store->id), 404);
        $auth->authorize($request->user(), $merchant, Permission::DeviceManage, $store);
        $data = $request->validate(['register_group_id' => ['nullable', 'uuid']]);
        $group = empty($data['register_group_id']) ? null : $this->lookup($merchant, 'register_groups', $data['register_group_id'], $store);
        abort_if($group !== null && $group->archived_at !== null && $group->id !== $register->getAttribute('register_group_id'), 422, 'Archived groups cannot be assigned to another register.');
        $register->forceFill(['register_group_id' => $group?->id])->save();

        return response()->json(['data' => ['saved' => true]]);
    }

    /**
     * @return array{0: User, 1: Store|null}
     */
    private function authorize(Request $request, Merchant $merchant, RoleAuthorizer $auth): array
    {
        $request->validate(['store_id' => ['nullable', 'uuid']]);
        $store = $request->filled('store_id') ? Store::query()->where('merchant_id', $merchant->id)->where('public_id', Uuid::fromString($request->input('store_id'))->getBytes())->firstOrFail() : null;
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $auth->authorize($user, $merchant, Permission::CatalogManage, $store);
        abort_unless($store !== null || $membership->role->value === 'owner', 403, 'Choose an assigned store.');
        if ($merchant->store_data_mode->value === 'independent') {
            abort_unless($store !== null, 422, 'Independent catalogues require a store.');
        }

        return [$user, $store];
    }

    private function lookup(Merchant $merchant, string $table, string $id, ?Store $store): \stdClass
    {
        abort_unless(Uuid::isValid($id), 422, 'Invalid classification identifier.');
        $q = DB::table($table)->where('merchant_id', $merchant->id)->where('public_id', Uuid::fromString($id)->getBytes());
        if ($table !== 'suppliers') {
            $q->where(fn ($q) => $q->whereNull('store_id')->when($store !== null, fn ($q) => $q->orWhere('store_id', $store->id)));
        }

        $row = $q->first();
        abort_unless($row instanceof \stdClass, 404);

        return $row;
    }
}
