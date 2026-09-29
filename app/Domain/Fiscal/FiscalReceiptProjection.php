<?php

namespace App\Domain\Fiscal;

use App\Models\FiscalDocument;
use App\Models\ReceiptSnapshot;

final class FiscalReceiptProjection
{
    /** @return array<string,mixed> */
    public function apply(ReceiptSnapshot $receipt): array
    {
        $payload = $receipt->payload;
        $document = FiscalDocument::query()->where('merchant_id', $receipt->merchant_id)->where('store_id', $receipt->store_id)->where('local_document_type', 'sale')->where('local_document_reference', $receipt->sale->public_id)->latest()->first();
        if ($document instanceof FiscalDocument) {
            $payload['fiscal_state'] = $document->state;
            $payload['fiscal_authority'] = 'FBR DI';
            $payload['fiscal_reference'] = $document->authority_reference;
            $payload['fiscal_qr'] = $document->qr_value;
        }

        return $payload;
    }
}
