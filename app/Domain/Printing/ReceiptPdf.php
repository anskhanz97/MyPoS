<?php

namespace App\Domain\Printing;

use App\Domain\Fiscal\FiscalReceiptProjection;
use App\Models\ReceiptSnapshot;
use Dompdf\Dompdf;
use Dompdf\Options;

final class ReceiptPdf
{
    public function __construct(private readonly FiscalReceiptProjection $fiscal) {}

    public function render(ReceiptSnapshot $receipt, string $copyType = 'original'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $payload = $this->fiscal->apply($receipt);
        $lineCount = count($payload['lines'] ?? []);
        $extraCount = 0;
        foreach ($payload['lines'] ?? [] as $line) {
            if (is_array($line)) {
                $extraCount += count($line['extras'] ?? []);
            }
        }
        $tenderCount = count($payload['tenders'] ?? []);
        $height = max(360, 265 + ($lineCount * 42) + ($extraCount * 14) + ($tenderCount * 16));
        $pdf->setPaper([0, 0, 226.77, $height]);
        $pdf->loadHtml(view('pdf.receipt', ['receipt' => $payload, 'copyType' => $copyType])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
