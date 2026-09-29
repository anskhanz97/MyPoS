<?php

namespace App\Domain\Checkout;

use App\Domain\Catalog\BarcodeResolver;
use App\Domain\Catalog\CatalogScope;
use App\Domain\Checkout\Enums\CartStatus;
use App\Domain\Inventory\Enums\InventoryDisposition;
use App\Domain\Loyalty\LoyaltyService;
use App\Domain\Pricing\PricingCalculator;
use App\Domain\Shared\Math\Decimal;
use App\Models\BatchSelectionSnapshot;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\CartLineExtra;
use App\Models\CartQuote;
use App\Models\ExtraOption;
use App\Models\InventoryLocation;
use App\Models\Merchant;
use App\Models\OperationalCustomer;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\Store;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CartWorkflow
{
    public function __construct(private readonly BarcodeResolver $barcodes, private readonly CatalogScope $catalogScope, private readonly PricingCalculator $pricing, private readonly LoyaltyService $loyalty) {}

    public function create(Merchant $merchant, Store $store, Register $register, InventoryLocation $location, User $actor): Cart
    {
        $this->scope($merchant, $store, $register, $location);

        return Cart::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'register_id' => $register->id, 'inventory_location_id' => $location->id, 'created_by_user_id' => $actor->id, 'last_edited_by_user_id' => $actor->id, 'reference' => 'CART-'.mb_strtoupper(substr((string) Str::ulid(), -12)), 'status' => CartStatus::Active, 'version' => 1, 'manual_cart_discount' => '0.00']);
    }

    /** @param array<string,mixed> $data */
    public function replace(Cart $cart, User $actor, array $data): Cart
    {
        return DB::transaction(function () use ($cart, $actor, $data): Cart {
            $locked = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->mutable($locked, (int) $data['expected_version']);
            $this->loyalty->releaseCart($locked);
            $customerId = null;
            if (isset($data['customer_id'])) {
                $customer = (new OperationalCustomer)->resolveRouteBinding((string) $data['customer_id']);
                abort_unless($customer instanceof OperationalCustomer && $customer->merchant_id === $locked->merchant_id && ($customer->store_id === null || $customer->store_id === $locked->store_id) && $customer->archived_at === null, 422, 'The customer is outside this cart store.');
                $customerId = $customer->id;
            }
            $locked->lines()->delete();
            foreach ($data['lines'] as $sequence => $input) {
                $this->addLine($locked, $input, $sequence + 1);
            }
            $locked->forceFill(['customer_id' => $customerId, 'coupon_codes' => array_values($data['coupon_codes'] ?? []), 'manual_cart_discount' => (string) ($data['manual_cart_discount'] ?? '0'), 'last_edited_by_user_id' => $actor->id, 'pricing_version' => null, 'version' => $locked->version + 1])->save();

            return $this->load($locked);
        });
    }

    public function scan(Merchant $merchant, Store $store, Cart $cart, User $actor, int $expectedVersion, string $code): Cart
    {
        $resolved = $this->barcodes->resolve($merchant, $store, $code, $this->catalogScope);

        return DB::transaction(function () use ($cart, $actor, $expectedVersion, $code, $resolved): Cart {
            $locked = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->mutable($locked, $expectedVersion);
            $this->loyalty->releaseCart($locked);
            $variant = $resolved['barcode']->variant()->with(['product.extraGroups.options', 'baseUnit'])->firstOrFail();
            abort_if($variant->product->extraGroups->isNotEmpty(), 422, 'Choose required extras before adding this product.');
            abort_if($variant->product->batch_tracking_enabled, 422, 'Select an eligible batch before adding this product.');
            $quantity = (string) ($resolved['quantity'] ?? '1.000000');
            $hash = $this->configurationHash($variant->id, $variant->base_unit_id, [], null, '0.00');
            $line = $locked->lines()->where('configuration_hash', $hash)->first();
            if ($line instanceof CartLine && $resolved['embedded_price'] === null) {
                $line->forceFill(['quantity_uom' => Decimal::of($line->quantity_uom)->plus($quantity)->toScale(6), 'quantity_base' => Decimal::of($line->quantity_base)->plus($quantity)->toScale(6), 'scan_code' => $code, 'scan_count' => $line->scan_count + 1])->save();
            } else {
                $this->addLine($locked, ['variant_id' => $variant->public_id, 'unit_id' => $variant->baseUnit->public_id, 'quantity' => $quantity, 'scan_code' => $code, 'scan_count' => 1], ((int) $locked->lines()->max('sequence')) + 1);
            }
            $locked->forceFill(['last_edited_by_user_id' => $actor->id, 'pricing_version' => null, 'version' => $locked->version + 1])->save();

            return $this->load($locked);
        });
    }

    public function transition(Cart $cart, User $actor, int $expectedVersion, CartStatus $to): Cart
    {
        return DB::transaction(function () use ($cart, $actor, $expectedVersion, $to): Cart {
            $locked = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            abort_unless(in_array([$locked->status, $to], [[CartStatus::Active, CartStatus::Suspended], [CartStatus::Suspended, CartStatus::Active]], true), 409, 'This cart transition is not allowed.');
            $this->version($locked, $expectedVersion);
            $locked->forceFill(['status' => $to, 'last_edited_by_user_id' => $actor->id, 'version' => $locked->version + 1, 'suspended_at' => $to === CartStatus::Suspended ? now() : $locked->suspended_at, 'resumed_at' => $to === CartStatus::Active ? now() : null])->save();

            return $this->load($locked);
        });
    }

    /** @return array{cart:Cart,quote:CartQuote} */
    public function quote(Merchant $merchant, Store $store, Cart $cart, User $actor, int $expectedVersion): array
    {
        return DB::transaction(function () use ($merchant, $store, $cart, $actor, $expectedVersion): array {
            $locked = Cart::query()->with(['lines.variant', 'lines.extras', 'lines.batchSelection', 'customer'])->lockForUpdate()->findOrFail($cart->id);
            $this->mutable($locked, $expectedVersion);
            abort_if($locked->lines->isEmpty(), 422, 'Add at least one item before quoting.');
            $inputs = [];
            foreach ($locked->lines as $line) {
                if ($line->batch_selection_snapshot_id !== null) {
                    $snapshot = BatchSelectionSnapshot::query()->findOrFail($line->batch_selection_snapshot_id);
                    abort_if($snapshot->expires_at <= now(), 409, 'A batch selection expired. Select stock again.');
                }
                $inputs[] = ['line_id' => $line->public_id, 'variant_id' => $line->variant->public_id, 'quantity' => $line->quantity_uom, 'manual_discount' => $line->manual_discount, 'unit_price_delta' => Decimal::fixed($line->extras->reduce(fn (BigDecimal $sum, CartLineExtra $extra): BigDecimal => $sum->plus($extra->unit_price_delta), BigDecimal::zero()), 2)];
            }
            $payload = ['lines' => $inputs, 'coupon_codes' => $locked->coupon_codes ?? [], 'manual_cart_discount' => $locked->manual_cart_discount, 'pricing_version' => $locked->pricing_version];
            if ($locked->customer !== null) {
                $payload['customer_reference'] = $locked->customer->public_id;
            }
            $loyalty = $this->loyalty->quoteData($locked);
            if ($loyalty !== null) {
                $payload['loyalty_discount'] = $loyalty['discount_amount'];
                $payload['loyalty_redemption'] = $loyalty;
            }
            $result = $this->pricing->quote($merchant, $store, array_filter($payload, fn ($value): bool => $value !== null));
            $next = ((int) $locked->quotes()->max('quote_version')) + 1;
            $json = json_encode($result, JSON_THROW_ON_ERROR);
            $quote = CartQuote::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'cart_id' => $locked->id, 'quoted_by_user_id' => $actor->id, 'quote_version' => $next, 'cart_version' => $locked->version, 'pricing_version' => $result['pricing_version'], 'content_hash' => hash('sha256', $json), 'payload' => $result, 'total' => $result['totals']['gross'], 'quoted_at' => now(), 'expires_at' => now()->addMinutes(5)]);
            $locked->forceFill(['pricing_version' => $result['pricing_version']])->save();

            return ['cart' => $this->load($locked), 'quote' => $quote];
        });
    }

    /** @param array<string,mixed> $input */
    private function addLine(Cart $cart, array $input, int $sequence): CartLine
    {
        $variant = (new ProductVariant)->resolveRouteBinding((string) $input['variant_id']);
        abort_unless($variant instanceof ProductVariant && $variant->merchant_id === $cart->merchant_id && ($variant->store_id === null || $variant->store_id === $cart->store_id) && $variant->archived_at === null, 422, 'A cart line variant is outside this catalog.');
        $variant->load(['product.extraGroups.options', 'baseUnit']);
        if ($variant->store_id === null) {
            abort_unless($variant->product->storeAvailabilities()->where('store_id', $cart->store_id)->where('is_available', true)->exists(), 422, 'A cart line is unavailable in this store.');
        }
        $unit = isset($input['unit_id']) ? (new UnitOfMeasure)->resolveRouteBinding((string) $input['unit_id']) : $variant->baseUnit;
        abort_unless($unit instanceof UnitOfMeasure && $unit->merchant_id === $cart->merchant_id && $unit->is_active, 422, 'The selected unit is unavailable.');
        $factor = $unit->id === $variant->base_unit_id ? '1.000000' : UnitConversion::query()->where('merchant_id', $cart->merchant_id)->where('product_variant_id', $variant->id)->where('from_unit_id', $unit->id)->value('factor_to_base');
        abort_unless(is_string($factor), 422, 'The selected unit has no conversion for this product.');
        $quantity = Decimal::of((string) $input['quantity']);
        abort_unless($quantity->isGreaterThan(BigDecimal::zero()), 422, 'Quantity must be greater than zero.');
        $quantityBase = $quantity->multipliedBy($factor)->toScale(6);
        $batchId = null;
        if (isset($input['batch_selection_id'])) {
            $snapshot = (new BatchSelectionSnapshot)->resolveRouteBinding((string) $input['batch_selection_id']);
            abort_unless($snapshot instanceof BatchSelectionSnapshot && $snapshot->merchant_id === $cart->merchant_id && $snapshot->store_id === $cart->store_id && $snapshot->inventory_location_id === $cart->inventory_location_id && $snapshot->product_variant_id === $variant->id && $snapshot->expires_at > now() && Decimal::of($snapshot->requested_quantity_base)->isEqualTo($quantityBase), 422, 'The batch selection is invalid or expired.');
            $batchId = $snapshot->id;
        }
        abort_if($variant->product->batch_tracking_enabled && $batchId === null, 422, 'This product requires an eligible batch selection.');
        $optionIds = array_values(array_unique($input['extra_option_ids'] ?? []));
        $options = collect();
        foreach ($optionIds as $optionId) {
            $option = (new ExtraOption)->resolveRouteBinding((string) $optionId);
            abort_unless($option instanceof ExtraOption && $option->merchant_id === $cart->merchant_id && $option->is_active, 422, 'An extra option is unavailable for this product.');
            $option->load('group');
            abort_unless($option->group->product_id === $variant->product_id, 422, 'An extra option is unavailable for this product.');
            $options->push($option);
        }
        foreach ($variant->product->extraGroups as $group) {
            $count = $options->where('extra_group_id', $group->id)->count();
            abort_unless($count >= $group->minimum_selected && $count <= $group->maximum_selected, 422, "Choose between {$group->minimum_selected} and {$group->maximum_selected} options for {$group->name}.");
        }
        $discount = (string) ($input['manual_discount'] ?? '0');
        $optionKeys = $options->map(fn (ExtraOption $option): int => $option->id)->sort()->values()->all();
        $hash = $this->configurationHash($variant->id, $unit->id, array_values($optionKeys), $batchId, $discount);
        $line = CartLine::query()->create(['merchant_id' => $cart->merchant_id, 'store_id' => $cart->store_id, 'cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'unit_id' => $unit->id, 'batch_selection_snapshot_id' => $batchId, 'sequence' => $sequence, 'product_name_snapshot' => $variant->product->name, 'variant_name_snapshot' => $variant->name, 'sku_snapshot' => $variant->sku, 'unit_symbol_snapshot' => $unit->symbol, 'quantity_uom' => $quantity->toScale(6), 'factor_to_base' => $factor, 'quantity_base' => $quantityBase, 'manual_discount' => $discount, 'scan_code' => $input['scan_code'] ?? null, 'scan_count' => $input['scan_count'] ?? 0, 'configuration_hash' => $hash]);
        foreach ($options->values() as $index => $option) {
            CartLineExtra::query()->create(['merchant_id' => $cart->merchant_id, 'store_id' => $cart->store_id, 'cart_line_id' => $line->id, 'extra_option_id' => $option->id, 'group_name_snapshot' => $option->group->name, 'option_name_snapshot' => $option->name, 'unit_price_delta' => $option->price_delta, 'sequence' => $index + 1]);
        }

        return $line;
    }

    /** @param list<int> $options */
    private function configurationHash(int $variant, int $unit, array $options, ?int $batch, string $discount): string
    {
        return hash('sha256', json_encode([$variant, $unit, $options, $batch, Decimal::fixed(Decimal::of($discount), 2)], JSON_THROW_ON_ERROR));
    }

    private function mutable(Cart $cart, int $version): void
    {
        abort_unless($cart->status === CartStatus::Active, 409, 'Only an active cart can be edited.');
        $this->version($cart, $version);
    }

    private function version(Cart $cart, int $expected): void
    {
        if ($cart->version !== $expected) {
            throw new ConflictHttpException('The cart changed in another session. Reload it before retrying.');
        }
    }

    private function scope(Merchant $merchant, Store $store, Register $register, InventoryLocation $location): void
    {
        abort_unless($store->merchant_id === $merchant->id && $register->merchant_id === $merchant->id && $register->store_id === $store->id && $location->merchant_id === $merchant->id && $location->store_id === $store->id && $location->disposition === InventoryDisposition::Sellable, 404);
    }

    private function load(Cart $cart): Cart
    {
        return $cart->load(['register', 'location', 'customer', 'lines.variant', 'lines.unit', 'lines.batchSelection', 'lines.extras.option']);
    }
}
