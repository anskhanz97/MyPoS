<?php

namespace App\Domain\Payroll;

use App\Models\PayslipSnapshot;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PayslipPdf
{
    public function render(PayslipSnapshot $snapshot): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('pdf.payslip', ['payslip' => $snapshot->payload])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }
}
