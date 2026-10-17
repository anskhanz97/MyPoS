<?php

namespace App\Domain\Fiscal;

use App\Jobs\SubmitFiscalDocument;
use App\Logging\OperationalLogger;
use App\Models\FiscalConfiguration;
use App\Models\FiscalDocument;
use App\Models\FiscalOutboxConsumption;
use App\Models\FiscalSubmissionAttempt;
use App\Models\OutboxEvent;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class FiscalService
{
    public function __construct(private readonly FbrDiV112Mapper $mapper, private readonly FiscalTransport $transport) {}

    public function queueSale(Sale $sale): ?FiscalDocument
    {
        $config = $this->active($sale->merchant_id, $sale->store_id);
        if (! $config) {
            app(OperationalLogger::class)->integration('fbr', 'fiscal.skipped', 'Fiscal submission is not required for this store.', ['merchant_id' => $sale->merchant_id, 'store_id' => $sale->store_id, 'subject' => 'sale:'.$sale->public_id, 'status' => 'skipped', 'context' => ['reason_code' => 'not_applicable']]);

            return null;
        }

        return $this->create($config, 'sale', $sale->public_id, $sale->number, $this->mapper->sale($config, $sale));
    }

    public function queueReturn(SaleReturn $return): ?FiscalDocument
    {
        $config = $this->active($return->merchant_id, $return->store_id);
        if (! $config) {
            app(OperationalLogger::class)->integration('fbr', 'fiscal.skipped', 'Fiscal return submission is not required for this store.', ['merchant_id' => $return->merchant_id, 'store_id' => $return->store_id, 'subject' => 'sale_return:'.$return->public_id, 'status' => 'skipped', 'context' => ['reason_code' => 'not_applicable']]);

            return null;
        }

        return $this->create($config, 'sale_return', $return->public_id, $return->number, $this->mapper->creditNote($config, $return));
    }

    public function submit(FiscalDocument $document): FiscalDocument
    {
        return DB::transaction(function () use ($document): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->state === 'accepted') {
                return $locked;
            } abort_unless($locked->state === 'queued', 409, 'Only queued fiscal documents can be submitted.');
            $locked->update(['state' => 'submitting']);

            return $locked;
        });
    }

    public function performSubmission(FiscalDocument $document): FiscalDocument
    {
        $document = $this->submit($document);
        if ($document->state === 'accepted') {
            return $document;
        }
        $result = $this->transport->submit($document->configuration, $document->payloadData());
        $attempt = $document->attempts()->count() + 1;
        FiscalSubmissionAttempt::query()->create(['fiscal_document_id' => $document->id, 'attempt_number' => $attempt, 'outcome' => $result->state, 'request_hash' => $document->payload_hash, 'response_hash' => $result->responseHash, 'http_status' => $result->httpStatus, 'authority_code' => $result->authorityCode, 'safe_message' => $result->safeMessage, 'attempted_at' => now()]);
        $changes = ['state' => $result->state, 'authority_reference' => $result->authorityReference, 'authority_dated_at' => $result->datedAt, 'qr_value' => $result->qrValue, 'failure_category' => $result->state === 'rejected' ? 'business_or_credential' : null, 'failure_code' => $result->authorityCode, 'safe_failure_message' => $result->safeMessage, 'unknown_since' => $result->state === 'unknown' ? now() : null, 'accepted_at' => $result->state === 'accepted' ? now() : null];
        $document->update($changes);

        return $document->refresh()->load(['configuration', 'attempts']);
    }

    public function retry(FiscalDocument $document): FiscalDocument
    {
        abort_unless($document->state === 'rejected', 409, 'Unknown outcomes must be reconciled, not blindly retried.');
        $document->update(['state' => 'queued']);
        Context::scope(fn () => Bus::dispatch(new SubmitFiscalDocument($document->id)), hidden: ['log_scope' => $document->logContext()]);

        return $document->refresh();
    }

    public function reconcile(FiscalDocument $document, User $actor, string $state, string $reference, string $reason): FiscalDocument
    {
        abort_unless($document->state === 'unknown', 409, 'Only unknown outcomes need manual reconciliation.');
        abort_unless(in_array($state, ['accepted', 'rejected'], true), 422);

        return DB::transaction(function () use ($document, $actor, $state, $reference, $reason): FiscalDocument {
            $locked = FiscalDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($locked->state === 'unknown', 409);
            $locked->update(['state' => $state, 'authority_reference' => $state === 'accepted' ? $reference : null, 'safe_failure_message' => $reason, 'accepted_at' => $state === 'accepted' ? now() : null, 'unknown_since' => null]);
            SecurityAuditEvent::query()->create(['merchant_id' => $locked->merchant_id, 'store_id' => $locked->store_id, 'actor_user_id' => $actor->id, 'event_code' => 'fiscal.document.reconciled', 'outcome' => 'succeeded', 'metadata' => ['document_id' => $locked->public_id, 'state' => $state, 'reference' => $reference, 'reason' => $reason], 'occurred_at' => now()]);

            return $locked->refresh();
        });
    }

    public function consumeOutbox(int $limit = 100): int
    {
        $count = 0;
        $events = OutboxEvent::query()->whereIn('event_type', ['sales.sale.finalized', 'fiscal.sale.returned'])->whereDoesntHave('fiscalConsumption')->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            DB::transaction(function () use ($event, &$count): void {
                if (FiscalOutboxConsumption::query()->where('outbox_event_id', $event->id)->exists()) {
                    return;
                } $document = null;
                if ($event->event_type === 'sales.sale.finalized') {
                    $sale = (new Sale)->resolveRouteBinding($event->aggregate_id);
                    if ($sale instanceof Sale) {
                        $document = $this->queueSale($sale);
                    }
                } else {
                    $return = (new SaleReturn)->resolveRouteBinding($event->aggregate_id);
                    if ($return instanceof SaleReturn) {
                        $document = $this->queueReturn($return);
                    }
                } FiscalOutboxConsumption::query()->create(['outbox_event_id' => $event->id, 'fiscal_document_id' => $document?->id, 'outcome' => $document ? 'queued' : 'not_applicable', 'consumed_at' => now()]);
                $count++;
            });
        }

        return $count;
    }

    /** @param array<string,mixed> $payload */
    private function create(FiscalConfiguration $config, string $type, string $reference, string $number, array $payload): FiscalDocument
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);
        $existing = FiscalDocument::query()->where('fiscal_configuration_id', $config->id)->where('local_document_type', $type)->where('local_document_reference', $reference)->first();
        if ($existing) {
            if (! hash_equals($existing->payload_hash, $hash)) {
                throw new ConflictHttpException('Fiscal source payload changed after queueing.');
            }

            return $existing;
        }
        $document = FiscalDocument::query()->create(['merchant_id' => $config->merchant_id, 'store_id' => $config->store_id, 'fiscal_configuration_id' => $config->id, 'local_document_type' => $type, 'local_document_reference' => $reference, 'local_document_number' => $number, 'state' => 'queued', 'payload_snapshot' => $payload, 'payload_hash' => $hash]);
        Context::scope(fn () => Bus::dispatch(new SubmitFiscalDocument($document->id)), hidden: ['log_scope' => $document->logContext()]);

        return $document;
    }

    private function active(int $merchantId, int $storeId): ?FiscalConfiguration
    {
        $config = FiscalConfiguration::query()->where('merchant_id', $merchantId)->where('store_id', $storeId)->where('applicability', 'required')->first();
        if (! $config) {
            return null;
        } abort_unless($config->state === 'active', 409, 'Fiscal-required store is not launch-ready.');
        abort_unless($config->environment === 'sandbox', 409, 'Production FBR activation requires licensed-integrator evidence not available in this build.');

        return $config;
    }
}
