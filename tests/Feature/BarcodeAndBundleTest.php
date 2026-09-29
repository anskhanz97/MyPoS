<?php

namespace Tests\Feature;

use App\Domain\Catalog\BundleStructure;
use App\Models\BundleComponent;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductStoreAvailability;
use App\Models\ProductVariant;
use App\Models\ScaleBarcodeFormat;
use App\Models\Store;
use App\Models\UnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BarcodeAndBundleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_weighted_barcode_preserves_leading_reference_and_decodes_quantity(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'KG', 'decimal_places' => 3]);
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'type' => 'weighted']);
        ProductStoreAvailability::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'product_id' => $product->id, 'is_available' => true]);
        $variant = ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'is_weighted' => true]);
        ProductBarcode::factory()->create(['merchant_id' => $merchant->id, 'product_variant_id' => $variant->id, 'code' => '00123', 'kind' => 'scale_reference']);
        ScaleBarcodeFormat::factory()->create(['merchant_id' => $merchant->id]);
        $body = '210012301250';
        $barcode = $body.$this->ean13CheckDigit($body);

        $this->actingAs($owner)->getJson($this->barcodeUrl($merchant, $store).'?barcode='.$barcode)->assertOk()
            ->assertJsonPath('data.scan.raw_barcode', $barcode)->assertJsonPath('data.scan.kind', 'scale')
            ->assertJsonPath('data.scan.quantity', '1.250000')->assertJsonPath('data.product.variants.0.barcodes.0.code', '00123');
        $this->actingAs($owner)->getJson($this->barcodeUrl($merchant, $store).'?barcode='.substr($barcode, 0, 12).'9')->assertUnprocessable();
    }

    public function test_scale_format_admin_validates_bounds_and_scope(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        $url = "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/catalog/scale-formats";
        $payload = ['scope' => 'store', 'name' => 'Store weight label', 'prefix' => '21', 'total_length' => 13, 'item_start' => 2, 'item_length' => 5, 'value_start' => 7, 'value_length' => 5, 'value_type' => 'quantity', 'value_decimal_places' => 3, 'checksum' => 'ean13'];
        $this->actingAs($owner)->postJson($url, $payload)->assertCreated()->assertJsonPath('data.scope', 'store');
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $payload['prefix'] = '22';
        $payload['value_start'] = 12;
        $this->actingAs($owner)->postJson($url, $payload)->assertUnprocessable();
    }

    public function test_carton_conversion_is_exact_and_invalid_factor_is_rejected(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        $piece = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'EA']);
        $carton = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id, 'code' => 'CTN']);
        $url = "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/catalog/products";
        $payload = ['type' => 'standard', 'name' => 'Carton item', 'variants' => [['name' => 'Default', 'sku' => 'CARTON-1', 'base_unit_id' => $piece->public_id, 'conversions' => [['unit_id' => $carton->public_id, 'factor_to_base' => '12.000000']]]]];
        $this->actingAs($owner)->postJson($url, $payload)->assertCreated()->assertJsonPath('data.variants.0.conversions.0.factor_to_base', '12.000000');
        $this->assertSame('12.000000', UnitConversion::query()->sole()->factor_to_base);

        $payload['variants'][0]['sku'] = 'CARTON-2';
        $payload['variants'][0]['conversions'][0]['factor_to_base'] = '0';
        $this->actingAs($owner)->postJson($url, $payload)->assertUnprocessable();
    }

    public function test_nested_bundles_expand_to_finished_items_and_cycles_are_rejected(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        $unit = UnitOfMeasure::factory()->create(['merchant_id' => $merchant->id]);
        $leaf = $this->variant($merchant, $unit, 'standard', 'LEAF');
        $bundleA = $this->variant($merchant, $unit, 'bundle', 'BUNDLE-A');
        $bundleB = $this->variant($merchant, $unit, 'bundle', 'BUNDLE-B');
        BundleComponent::query()->create(['merchant_id' => $merchant->id, 'bundle_variant_id' => $bundleA->id, 'component_variant_id' => $leaf->id, 'quantity_base' => '2.000000']);
        BundleComponent::query()->create(['merchant_id' => $merchant->id, 'bundle_variant_id' => $bundleB->id, 'component_variant_id' => $bundleA->id, 'quantity_base' => '3.000000']);
        $expanded = app(BundleStructure::class)->expand($bundleB, '2.000000');
        $this->assertSame('12.000000', $expanded[$leaf->id]);

        $url = "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/catalog/variants/{$bundleA->public_id}/bundle-components";
        $this->actingAs($owner)->putJson($url, ['expected_structure_version' => 1, 'components' => [['variant_id' => $bundleB->public_id, 'quantity_base' => '1.000000']]])
            ->assertConflict()->assertJsonPath('code', 'invalid_state_transition');
        $this->assertDatabaseHas('bundle_components', ['bundle_variant_id' => $bundleA->id, 'component_variant_id' => $leaf->id]);
    }

    /** @return array{User, Merchant, Store} */
    private function fixture(): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        MerchantMembership::factory()->owner()->for($merchant)->for($owner)->create();

        return [$owner, $merchant, $store];
    }

    private function variant(Merchant $merchant, UnitOfMeasure $unit, string $type, string $sku): ProductVariant
    {
        $product = Product::factory()->create(['merchant_id' => $merchant->id, 'type' => $type]);

        return ProductVariant::factory()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'base_unit_id' => $unit->id, 'sku' => $sku]);
    }

    private function ean13CheckDigit(string $body): int
    {
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $body[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function barcodeUrl(Merchant $merchant, Store $store): string
    {
        return "/api/v1/merchants/{$merchant->public_id}/stores/{$store->public_id}/catalog/barcode";
    }
}
