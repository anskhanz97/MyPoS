<?php

namespace App\Domain\Printing;

final class ThermalReceiptRenderer
{
    /** @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public function render(array $receipt, string $copyType): array
    {
        $lines = [];
        $lines[] = ['align' => 'center', 'bold' => true, 'text' => (string) data_get($receipt, 'merchant.name')];
        $lines[] = ['align' => 'center', 'text' => (string) data_get($receipt, 'store.name').' · '.(string) data_get($receipt, 'register.code')];
        if ($copyType === 'reprint') {
            $lines[] = ['align' => 'center', 'bold' => true, 'text' => '*** COPY / REPRINT ***'];
        }
        $lines[] = ['align' => 'left', 'text' => 'Sale '.(string) ($receipt['number'] ?? '')];
        $lines[] = ['align' => 'left', 'text' => (string) ($receipt['finalized_at'] ?? $receipt['business_date'] ?? '')];
        foreach (($receipt['lines'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = ['align' => 'left', 'text' => (string) ($line['description'] ?? '')];
            $lines[] = ['columns' => [(string) ($line['quantity'] ?? '').' '.(string) ($line['unit'] ?? ''), 'PKR '.(string) ($line['gross'] ?? '0.00')]];
            foreach (($line['extras'] ?? []) as $extra) {
                if (is_array($extra)) {
                    $lines[] = ['align' => 'left', 'text' => '  + '.(string) ($extra['name'] ?? '').'  PKR '.(string) ($extra['price_delta'] ?? '0.00')];
                }
            }
        }
        $lines[] = ['rule' => true];
        $lines[] = ['columns' => ['Subtotal', 'PKR '.(string) data_get($receipt, 'totals.subtotal')]];
        $lines[] = ['columns' => ['Discount', '- PKR '.(string) data_get($receipt, 'totals.discount')]];
        $lines[] = ['columns' => ['Tax', 'PKR '.(string) data_get($receipt, 'totals.tax')]];
        $lines[] = ['columns' => ['TOTAL', 'PKR '.(string) data_get($receipt, 'totals.gross')], 'bold' => true];
        foreach (($receipt['tenders'] ?? []) as $tender) {
            if (is_array($tender)) {
                $lines[] = ['columns' => [str_replace('_', ' ', mb_strtoupper((string) ($tender['type'] ?? 'tender'))), 'PKR '.(string) ($tender['amount'] ?? '0.00')]];
            }
        }
        $lines[] = ['columns' => ['Change', 'PKR '.(string) ($receipt['cash_change'] ?? '0.00')]];
        $lines[] = ['align' => 'center', 'text' => ($receipt['source'] ?? 'online') === 'offline' ? 'OFFLINE SALE · SERVER RECONCILIATION '.mb_strtoupper((string) ($receipt['fiscal_state'] ?? 'pending')) : 'Thank you'];

        return ['schema_version' => 1, 'paper_width_mm' => 80, 'encoding' => 'utf-8', 'copy_type' => $copyType, 'commands' => $lines, 'cut' => true];
    }
}
