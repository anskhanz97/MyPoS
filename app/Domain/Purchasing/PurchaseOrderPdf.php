<?php

namespace App\Domain\Purchasing;

use App\Models\PurchaseOrder;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PurchaseOrderPdf
{
    public function render(PurchaseOrder $order): string
    {
        $order->loadMissing(['store.merchant', 'supplier', 'lines.variant.product', 'lines.purchaseUnit']);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('pdf.purchase-order', ['order' => $order])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
