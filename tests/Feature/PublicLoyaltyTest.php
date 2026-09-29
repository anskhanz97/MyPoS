<?php

namespace Tests\Feature;

use App\Domain\Loyalty\LoyaltyService;
use App\Models\LoyaltyMember;
use App\Models\LoyaltyPointEntry;
use App\Models\LoyaltyPublicEnrollment;
use App\Models\Merchant;
use App\Models\PublicLoyaltyAccessEvent;
use App\Models\Store;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class PublicLoyaltyTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_one_form_joins_or_returns_and_refresh_lookup_is_read_only(): void
    {
        $fixture = $this->pricingFixture();
        $program = app(LoyaltyService::class)->publish($fixture['merchant'], $fixture['owner'], 'Everyday Points', 'everyday-public', '0.01000000', 365, [['name' => 'Tea reward', 'points_required' => '10.0000', 'discount_amount' => '50.00']]);
        $base = '/api/v1/public/loyalty/'.$program->slug;
        $this->getJson($base)->assertOk()->assertJsonPath('data.name', 'Everyday Points')->assertJsonMissing(['discount_amount' => '50.00']);
        $key = (string) Str::uuid();
        $payload = ['phone' => '0300 1234567', 'display_name' => 'Ayesha Khan', 'store_id' => $fixture['store']->public_id, 'marketing_consent' => false];
        $created = $this->withHeader('Idempotency-Key', $key)->postJson($base.'/join', $payload)->assertCreated()
            ->assertJsonPath('already_member', false)->assertJsonPath('replayed', false)->assertJsonPath('data.masked_name', 'A••••• K•••')
            ->assertJsonPath('data.masked_phone', '+92 ••• ••• 4567')->json();
        $this->assertSame(['member_public_id', 'masked_name', 'masked_phone', 'points_balance', 'available_rewards'], array_keys($created['data']));
        $this->assertSame(1, LoyaltyMember::query()->where('merchant_id', $fixture['merchant']->id)->count());
        $this->assertSame(1, LoyaltyPublicEnrollment::query()->count());
        $this->withHeader('Idempotency-Key', $key)->postJson($base.'/join', $payload)->assertOk()->assertJsonPath('already_member', true)->assertJsonPath('replayed', true)->assertJsonPath('data.member_public_id', $created['data']['member_public_id']);
        $this->assertSame(1, LoyaltyMember::query()->count());
        $this->assertSame(1, LoyaltyPublicEnrollment::query()->count());
        $changed = $payload;
        $changed['display_name'] = 'Changed Name';
        $this->withHeader('Idempotency-Key', $key)->postJson($base.'/join', $changed)->assertConflict();

        $membersBefore = LoyaltyMember::query()->count();
        $ledgerBefore = LoyaltyPointEntry::query()->count();
        $enrollmentsBefore = LoyaltyPublicEnrollment::query()->count();
        $lookup = $this->postJson($base.'/lookup', ['phone' => '+92 300 1234567'])->assertOk()->json('data');
        $this->assertSame(['member_public_id', 'masked_name', 'masked_phone', 'points_balance', 'available_rewards'], array_keys($lookup));
        $this->postJson($base.'/lookup', ['phone' => '03001234567'])->assertOk()->assertJsonPath('data.member_public_id', $created['data']['member_public_id']);
        $this->assertSame($membersBefore, LoyaltyMember::query()->count());
        $this->assertSame($ledgerBefore, LoyaltyPointEntry::query()->count());
        $this->assertSame($enrollmentsBefore, LoyaltyPublicEnrollment::query()->count());
        $this->assertSame(4, PublicLoyaltyAccessEvent::query()->count());
        $this->assertNull(PublicLoyaltyAccessEvent::query()->firstOrFail()->getAttribute('phone'));

        $otherMerchant = Merchant::factory()->create();
        $otherStore = Store::factory()->for($otherMerchant)->create();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/join', array_replace($payload, ['store_id' => $otherStore->public_id]))->assertUnprocessable();
        $program->forceFill(['is_enabled' => false])->save();
        $this->getJson($base)->assertNotFound();
        $this->postJson($base.'/lookup', ['phone' => '03001234567'])->assertNotFound();
    }

    public function test_lookup_is_bounded_and_unknown_phone_discloses_no_member_fields(): void
    {
        $fixture = $this->pricingFixture();
        $program = app(LoyaltyService::class)->publish($fixture['merchant'], $fixture['owner'], 'Limited', 'limited-public', '0.01', null, []);
        $url = '/api/v1/public/loyalty/'.$program->slug.'/lookup';
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.17'])->postJson($url, ['phone' => '03009999999'])->assertNotFound()->assertJsonMissing(['member_public_id']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.17'])->postJson($url, ['phone' => '03009999999'])->assertTooManyRequests();
        $this->assertSame(10, PublicLoyaltyAccessEvent::query()->where('outcome', 'not_found')->count());
    }
}
