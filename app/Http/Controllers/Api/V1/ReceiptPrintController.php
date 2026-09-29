<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Printing\CreatePrintJob;
use App\Domain\Printing\ReceiptPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePrintJobRequest;
use App\Models\Merchant;
use App\Models\PrintAttempt;
use App\Models\PrintJob;
use App\Models\ReceiptSnapshot;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptPrintController extends Controller
{
    public function store(CreatePrintJobRequest $request, ReceiptSnapshot $receipt, RoleAuthorizer $authorizer, CreatePrintJob $creator): JsonResponse
    {
        [$merchant, $store] = $this->scope($receipt);
        $user = $this->user($request);
        $authorizer->authorize($user, $merchant, Permission::SaleView, $store);
        $data = $request->validated();
        $register = (new Register)->resolveRouteBinding((string) $data['register_id']);
        abort_unless($register instanceof Register && $register->merchant_id === $merchant->id && $register->store_id === $store->id, 422, 'The print register is outside this receipt store.');
        $result = $creator->handle($receipt, $register, $user, (string) $data['idempotency_key'], (string) $data['copy_type'], $data['reason'] ?? null, $data['printer_name'] ?? null);

        return response()->json(['data' => $this->present($result['job']), 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, ReceiptSnapshot $receipt, PrintJob $printJob, RoleAuthorizer $authorizer): JsonResponse
    {
        [$merchant, $store] = $this->scope($receipt);
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        abort_unless($printJob->receipt_snapshot_id === $receipt->id, 404);

        return response()->json(['data' => $this->present($printJob), 'replayed' => false]);
    }

    public function pdf(Request $request, ReceiptSnapshot $receipt, RoleAuthorizer $authorizer, ReceiptPdf $pdf): Response
    {
        [$merchant, $store] = $this->scope($receipt);
        $authorizer->authorize($this->user($request), $merchant, Permission::SaleView, $store);
        $data = $request->validate(['copy_type' => ['nullable', 'in:original,reprint']]);
        $copy = (string) ($data['copy_type'] ?? 'original');

        return response($pdf->render($receipt, $copy), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$receipt->payload['number'].'-'.$copy.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{Merchant,Store} */
    private function scope(ReceiptSnapshot $receipt): array
    {
        $receipt->loadMissing('sale.register.store.merchant');
        abort_unless($receipt->sale->merchant_id === $receipt->merchant_id && $receipt->sale->store_id === $receipt->store_id, 404);

        return [$receipt->sale->register->store->merchant, $receipt->sale->register->store];
    }

    /** @return array<string,mixed> */
    private function present(PrintJob $job): array
    {
        $job->loadMissing(['attempts', 'receipt', 'register', 'drawerCommand']);

        return ['id' => $job->public_id, 'receipt_id' => $job->receipt->public_id, 'register_id' => $job->register->public_id, 'copy_type' => $job->copy_type, 'state' => $job->state, 'printer_name' => $job->printer_name, 'helper_protocol' => $job->helper_protocol, 'render_payload' => $job->render_payload, 'signed_authorization' => $job->signed_authorization, 'drawer_command' => $job->drawerCommand === null ? null : ['id' => $job->drawerCommand->public_id, 'kind' => $job->drawerCommand->kind, 'state' => $job->drawerCommand->state, 'reason' => $job->drawerCommand->reason, 'signed_authorization' => $job->drawerCommand->signed_authorization, 'expires_at' => $job->drawerCommand->expires_at->toAtomString(), 'reported_at' => $job->drawerCommand->reported_at?->toAtomString()], 'reason' => $job->reason, 'expires_at' => $job->expires_at->toAtomString(), 'completed_at' => $job->completed_at?->toAtomString(), 'attempts' => $job->attempts->map(fn (PrintAttempt $attempt): array => ['id' => $attempt->public_id, 'attempt_number' => $attempt->attempt_number, 'outcome' => $attempt->outcome, 'printer_name' => $attempt->printer_name, 'error_code' => $attempt->error_code, 'safe_message' => $attempt->safe_message, 'reported_at' => $attempt->reported_at->toAtomString()])->all()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
