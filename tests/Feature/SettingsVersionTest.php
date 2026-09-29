<?php

namespace Tests\Feature;

use App\Domain\Devices\SettingsResolver;
use App\Models\Merchant;
use App\Models\MerchantMembership;
use App\Models\SettingsVersion;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SettingsVersionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_merchant_defaults_and_store_overrides_resolve_with_a_composite_version(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        $merchantPayload = ['timezone' => 'Asia/Karachi', 'business_day_cutoff' => '04:00', 'promotion_mode' => 'manual', 'features' => ['customer_display' => false], 'hardware' => ['printer' => ['transport' => 'none', 'profile' => null]]];
        $this->actingAs($owner)->postJson($this->settingsUrl($merchant), ['expected_version' => 0, 'payload' => $merchantPayload])
            ->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.scope', 'merchant');
        $this->actingAs($owner)->postJson($this->settingsUrl($merchant, $store), ['expected_version' => 0, 'payload' => ['promotion_mode' => 'advanced', 'features' => ['customer_display' => true]]])
            ->assertCreated()->assertJsonPath('data.scope', 'store');

        $resolved = app(SettingsResolver::class)->current($merchant, $store);
        $this->assertSame('m1-s1', $resolved['version']);
        $this->assertSame('Asia/Karachi', $resolved['payload']['timezone']);
        $this->assertSame('advanced', $resolved['payload']['promotion_mode']);
        $this->assertTrue($resolved['payload']['features']['customer_display']);
        $this->assertSame('none', $resolved['payload']['hardware']['printer']['transport']);
    }

    public function test_history_is_append_only_and_optimistic_version_conflicts_are_rejected(): void
    {
        [$owner, $merchant] = $this->fixture();
        $url = $this->settingsUrl($merchant);
        $this->actingAs($owner)->postJson($url, ['expected_version' => 0, 'payload' => ['promotion_mode' => 'manual']])->assertCreated();
        $this->actingAs($owner)->postJson($url, ['expected_version' => 1, 'payload' => ['promotion_mode' => 'advanced']])->assertCreated();
        $this->actingAs($owner)->postJson($url, ['expected_version' => 1, 'payload' => ['promotion_mode' => 'manual']])->assertConflict();
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.version', 2)->assertJsonPath('data.1.version', 1);
        $this->assertSame(2, SettingsVersion::query()->where('merchant_id', $merchant->id)->count());
        $this->assertDatabaseHas('security_audit_events', ['event_code' => 'settings.version.published', 'outcome' => 'succeeded']);
    }

    public function test_future_settings_are_retained_but_do_not_take_effect_early(): void
    {
        [$owner, $merchant, $store] = $this->fixture();
        SettingsVersion::factory()->create(['merchant_id' => $merchant->id, 'created_by_user_id' => $owner->id, 'version' => 1, 'payload' => ['promotion_mode' => 'manual']]);
        $this->actingAs($owner)->postJson($this->settingsUrl($merchant), ['expected_version' => 1, 'effective_at' => now()->addDay()->toAtomString(), 'payload' => ['promotion_mode' => 'advanced']])
            ->assertCreated()->assertJsonPath('data.version', 2);

        $this->assertSame('manual', app(SettingsResolver::class)->current($merchant, $store)['payload']['promotion_mode']);
        $this->travel(2)->days();
        $this->assertSame('advanced', app(SettingsResolver::class)->current($merchant, $store)['payload']['promotion_mode']);
    }

    public function test_settings_reject_cross_merchant_store_and_non_owner_access(): void
    {
        [$owner, $merchant] = $this->fixture();
        [, $otherMerchant, $otherStore] = $this->fixture();
        $manager = User::factory()->create();
        MerchantMembership::factory()->for($merchant)->for($manager)->create();
        $payload = ['expected_version' => 0, 'payload' => ['promotion_mode' => 'manual']];

        $this->actingAs($owner)->postJson($this->settingsUrl($merchant, $otherStore), $payload)->assertNotFound();
        $this->actingAs($manager)->postJson($this->settingsUrl($merchant), $payload)->assertForbidden();
        $this->assertDatabaseMissing('settings_versions', ['merchant_id' => $merchant->id, 'store_id' => $otherStore->id]);
        $this->assertNotSame($merchant->id, $otherMerchant->id);
    }

    public function test_settings_contract_rejects_unknown_and_register_level_configuration(): void
    {
        [$owner, $merchant] = $this->fixture();
        $this->actingAs($owner)->postJson($this->settingsUrl($merchant), [
            'expected_version' => 0,
            'payload' => ['promotion_mode' => 'manual', 'register_id' => 'forbidden', 'device_key' => 'must-not-persist'],
        ])->assertUnprocessable()->assertJsonPath('field_errors.0.pointer', '/payload');
        $this->assertSame(0, SettingsVersion::query()->where('merchant_id', $merchant->id)->count());
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

    private function settingsUrl(Merchant $merchant, ?Store $store = null): string
    {
        return "/api/v1/merchants/{$merchant->public_id}/settings".($store === null ? '' : "/{$store->public_id}");
    }
}
