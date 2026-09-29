<?php

namespace App\Domain\Printing;

use App\Domain\Offline\CanonicalPayload;
use App\Models\DrawerCommand;
use App\Models\OutboxEvent;
use App\Models\PrintJob;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CreatePrintJob
{
    public function __construct(private readonly CanonicalPayload $canonical, private readonly ThermalReceiptRenderer $renderer, private readonly PrintAuthorizationSigner $signer) {}

    /** @return array{job:PrintJob,replayed:bool} */
    public function handle(ReceiptSnapshot $receipt, Register $register, User $actor, string $idempotencyKey, string $copyType, ?string $reason, ?string $printerName): array
    {
        $input = ['receipt_id' => $receipt->public_id, 'register_id' => $register->public_id, 'copy_type' => $copyType, 'reason' => $reason, 'printer_name' => $printerName];
        $hash = $this->canonical->hash($input);

        return DB::transaction(function () use ($receipt, $register, $actor, $idempotencyKey, $copyType, $reason, $printerName, $hash): array {
            Register::query()->lockForUpdate()->findOrFail($register->id);
            abort_unless(($register->capabilities['printer'] ?? false) === true, 409, 'This register has no configured receipt printer capability.');
            $existing = PrintJob::query()->where('merchant_id', $receipt->merchant_id)->where('register_id', $register->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof PrintJob) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('The print idempotency key was reused with different data.');
                }

                return ['job' => $existing, 'replayed' => true];
            }
            abort_unless($receipt->store_id === $register->store_id && $receipt->sale->register_id === $register->id, 422, 'The receipt is outside this register.');
            if ($copyType === 'original') {
                abort_if(PrintJob::query()->where('receipt_snapshot_id', $receipt->id)->where('copy_type', 'original')->exists(), 409, 'The original print job already exists. Use an explicit reprint.');
            }
            abort_if($copyType === 'reprint' && ($reason === null || mb_strlen(trim($reason)) < 5), 422, 'A reprint reason is required.');
            $render = $this->renderer->render($receipt->payload, $copyType);
            $jobId = (string) Str::uuid7();
            $expires = now()->addMinutes(10);
            $authorization = $this->signer->sign(['kind' => 'receipt_print', 'job_id' => $jobId, 'merchant_id' => data_get($receipt->payload, 'merchant.id'), 'store_id' => data_get($receipt->payload, 'store.id'), 'receipt_id' => $receipt->public_id, 'register_id' => $register->public_id, 'fencing_epoch' => $register->fencing_epoch, 'render_hash' => $this->canonical->hash($render), 'copy_type' => $copyType, 'printer_name' => $printerName, 'expires_at' => $expires->toAtomString(), 'nonce' => (string) Str::uuid()]);
            $job = PrintJob::query()->create(['public_id' => $jobId, 'merchant_id' => $receipt->merchant_id, 'store_id' => $receipt->store_id, 'register_id' => $register->id, 'receipt_snapshot_id' => $receipt->id, 'requested_by_user_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash, 'copy_type' => $copyType, 'original_guard' => $copyType === 'original' ? 1 : null, 'state' => 'created', 'printer_name' => $printerName, 'helper_protocol' => 1, 'render_payload' => $render, 'signed_authorization' => $authorization, 'reason' => $reason, 'expires_at' => $expires]);
            $hasCash = false;
            foreach ($receipt->payload['tenders'] ?? [] as $tender) {
                if (is_array($tender) && ($tender['type'] ?? null) === 'cash') {
                    $hasCash = true;
                    break;
                }
            }
            if ($copyType === 'original' && $hasCash && ($register->capabilities['cash_drawer'] ?? false)) {
                $drawerId = (string) Str::uuid7();
                $drawerPayload = ['kind' => 'sale_cash', 'drawer_command_id' => $drawerId, 'job_id' => $job->public_id, 'merchant_id' => data_get($receipt->payload, 'merchant.id'), 'store_id' => data_get($receipt->payload, 'store.id'), 'register_id' => $register->public_id, 'fencing_epoch' => $register->fencing_epoch, 'expires_at' => $expires->toAtomString(), 'nonce' => (string) Str::uuid()];
                DrawerCommand::query()->create(['public_id' => $drawerId, 'merchant_id' => $receipt->merchant_id, 'store_id' => $receipt->store_id, 'register_id' => $register->id, 'cash_session_id' => $receipt->sale->cash_session_id, 'requested_by_user_id' => $actor->id, 'print_job_id' => $job->id, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $this->canonical->hash($drawerPayload), 'kind' => 'sale_cash', 'reason' => 'Drawer access for '.$receipt->payload['number'], 'state' => 'authorized', 'signed_authorization' => $this->signer->sign($drawerPayload), 'expires_at' => $expires]);
            }
            OutboxEvent::query()->create(['event_id' => (string) Str::uuid(), 'merchant_id' => $receipt->merchant_id, 'store_id' => $receipt->store_id, 'event_type' => 'printing.job.requested', 'aggregate_type' => 'print_job', 'aggregate_id' => $job->public_id, 'payload' => ['print_job_id' => $job->public_id, 'receipt_id' => $receipt->public_id, 'register_id' => $register->public_id, 'copy_type' => $copyType], 'occurred_at' => now(), 'available_at' => now()]);

            return ['job' => $job, 'replayed' => false];
        }, attempts: 3);
    }
}
