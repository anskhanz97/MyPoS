<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalReceiptProjection;
use App\Domain\Fiscal\FiscalService;
use App\Jobs\SubmitFiscalDocument;
use App\Models\FiscalConfiguration;
use App\Models\FiscalDocument;
use App\Models\FiscalProductMapping;
use App\Models\InventoryLocation;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Sale;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsPricingFixture;
use Tests\TestCase;

class FiscalTest extends TestCase
{
    use BuildsPricingFixture, LazilyRefreshDatabase;

    public function test_fbr_sandbox_adapter_preserves_accepted_rejected_and_unknown_outcomes(): void
    {
        Queue::fake();
        $fixture = $this->saleFixture();
        $config = FiscalConfiguration::query()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'authority' => 'fbr_di', 'adapter_version' => 'fbr-di-v1.12', 'environment' => 'sandbox', 'applicability' => 'required', 'state' => 'active', 'tax_registration_number' => '1234567-8', 'outlet_reference' => 'POS-1', 'outage_policy' => 'block_sale', 'profile' => ['seller_province' => 'Punjab', 'seller_address' => 'Lahore', 'scenario_id' => 'SN026'], 'credentials' => ['bearer_token' => 'sandbox-secret'], 'evidence_hash' => str_repeat('a', 64), 'sandbox_verified_at' => now(), 'version' => 1]);
        FiscalProductMapping::query()->create(['fiscal_configuration_id' => $config->id, 'product_variant_id' => $fixture['first']->id, 'hs_code' => '0902.1000', 'uom_code' => 'Numbers, pieces, units', 'rate_label' => '18%', 'sale_type' => 'Goods at standard rate (default)', 'effective_from' => '2026-01-01']);
        $service = app(FiscalService::class);
        $this->assertSame(1, $service->consumeOutbox());
        $this->assertSame(0, $service->consumeOutbox());
        Queue::assertPushed(SubmitFiscalDocument::class, 1);
        $document = FiscalDocument::query()->firstOrFail();
        $this->assertSame('queued', $document->state);
        $this->assertSame('SN026', $document->payloadData()['scenarioId']);
        $this->assertSame('0902.1000', $document->payloadData()['items'][0]['hsCode']);
        Http::fakeSequence('*')
            ->push(['invoiceNumber' => 'FBR-INV-1', 'dated' => '2026-09-29T12:00:00+05:00', 'validationResponse' => ['status' => 'Valid']], 200)
            ->push(['validationResponse' => ['status' => 'Invalid', 'errorCode' => '0042']], 422)
            ->pushFailedConnection('timeout');
        $accepted = $service->performSubmission($document);
        $this->assertSame('accepted', $accepted->state);
        $this->assertSame('FBR-INV-1', $accepted->authority_reference);
        $this->assertSame(1, $accepted->attempts()->count());
        $payload = app(FiscalReceiptProjection::class)->apply($fixture['receipt']);
        $this->assertSame('accepted', $payload['fiscal_state']);
        $this->assertSame('FBR-INV-1', $payload['fiscal_reference']);
        $saleUrl = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$fixture['register']->public_id}/sales/{$fixture['sale']->public_id}";
        $this->actingAs($fixture['cashier'])->getJson($saleUrl)->assertOk()->assertJsonPath('data.fiscal_state', 'accepted')->assertJsonPath('data.fiscal_reference', 'FBR-INV-1');

        $rejected = $this->document($config, 'REJECT-1');
        $rejected = $service->performSubmission($rejected);
        $this->assertSame('rejected', $rejected->state);
        $this->assertSame('0042', $rejected->failure_code);
        $service->retry($rejected);
        Queue::assertPushed(SubmitFiscalDocument::class, 2);

        $unknown = $this->document($config, 'UNKNOWN-1');
        $unknown = $service->performSubmission($unknown);
        $this->assertSame('unknown', $unknown->state);
        $this->assertNotNull($unknown->unknown_since);
        try {
            $service->retry($unknown);
            $this->fail('Unknown outcome must not be blindly retried.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $reconcile = "/api/v1/merchants/{$fixture['merchant']->public_id}/fiscal-documents/{$unknown->public_id}/reconcile";
        $this->actingAs($fixture['owner'])->postJson($reconcile, ['state' => 'accepted', 'authority_reference' => 'FBR-FOUND-1', 'reason' => 'Licensed integrator confirmed the existing authority invoice.'])->assertOk()->assertJsonPath('data.state', 'accepted');
    }

    public function test_configuration_blocks_unverified_authorities_and_hides_credentials(): void
    {
        $fixture = $this->pricingFixture();
        $url = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/fiscal";
        $base = ['environment' => 'sandbox', 'applicability' => 'required', 'state' => 'active', 'tax_registration_number' => '123', 'outlet_reference' => 'POS-1', 'outage_policy' => 'block_sale', 'profile' => ['seller_province' => 'Punjab'], 'bearer_token' => 'secret', 'evidence_hash' => str_repeat('b', 64), 'sandbox_verified_at' => now()->toAtomString(), 'expected_version' => 0];
        $this->actingAs($fixture['owner'])->putJson($url, ['authority' => 'kpra'] + $base)->assertUnprocessable();
        $this->actingAs($fixture['owner'])->putJson($url, ['authority' => 'fbr_di'] + $base)->assertCreated()->assertJsonPath('data.credential_configured', true)->assertJsonMissing(['bearer_token' => 'secret']);
        $production = ['authority' => 'fbr_di', 'environment' => 'production', 'expected_version' => 1] + $base;
        $this->actingAs($fixture['owner'])->putJson($url, $production)->assertUnprocessable();
        $this->actingAs($fixture['manager'])->getJson($url)->assertOk()->assertJsonMissing(['bearer_token' => 'secret']);
    }

    private function document(FiscalConfiguration $config, string $reference): FiscalDocument
    {
        $payload = ['invoiceType' => 'Sale Invoice', 'invoiceRefNo' => $reference, 'items' => []];

        return FiscalDocument::query()->create(['merchant_id' => $config->merchant_id, 'store_id' => $config->store_id, 'fiscal_configuration_id' => $config->id, 'local_document_type' => 'sale', 'local_document_reference' => (string) Str::uuid(), 'local_document_number' => $reference, 'state' => 'queued', 'payload_snapshot' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))]);
    }

    /** @return array<string,mixed> */
    private function saleFixture(): array
    {
        $fixture = $this->pricingFixture();
        $register = Register::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id]);
        $location = InventoryLocation::factory()->create(['merchant_id' => $fixture['merchant']->id, 'store_id' => $fixture['store']->id, 'code' => 'MAIN', 'name' => 'Main', 'disposition' => 'sellable']);
        $this->actingAs($fixture['owner'])->postJson($this->pricingUrl($fixture).'/versions', $this->pricingPayload($fixture))->assertCreated();
        $base = "/api/v1/merchants/{$fixture['merchant']->public_id}/stores/{$fixture['store']->public_id}/registers/{$register->public_id}";
        $session = $this->actingAs($fixture['cashier'])->postJson($base.'/cash-sessions', ['idempotency_key' => (string) Str::uuid(), 'opening_amount' => '1000.00', 'opened_at' => now()->toAtomString()])->assertCreated()->json('data');
        $cart = $this->actingAs($fixture['cashier'])->postJson($base.'/carts', ['inventory_location_id' => $location->public_id])->assertCreated()->json('data');
        $this->actingAs($fixture['cashier'])->putJson($base.'/carts/'.$cart['id'], ['expected_version' => 1, 'lines' => [['variant_id' => $fixture['first']->public_id, 'quantity' => '1.000000']]])->assertOk();
        $quote = $this->actingAs($fixture['cashier'])->postJson($base.'/carts/'.$cart['id'].'/quotes', ['expected_version' => 2])->assertCreated()->json('quote');
        $this->actingAs($fixture['cashier'])->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base.'/sales', ['client_transaction_id' => (string) Str::uuid(), 'quote_id' => $quote['id'], 'quote_version' => $quote['quote_version'], 'register_session_id' => $session['id'], 'tenders' => [['tender_id' => (string) Str::uuid(), 'type' => 'cash', 'amount' => '118.00', 'confirmation_source' => 'not_applicable']], 'cash_change' => '0.00'])->assertCreated();

        return [...$fixture, 'register' => $register, 'sale' => Sale::query()->firstOrFail(), 'receipt' => ReceiptSnapshot::query()->firstOrFail()];
    }
}
