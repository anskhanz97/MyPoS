<?php

namespace App\Domain\Fiscal;

use App\Models\FiscalConfiguration;
use App\Models\FiscalProductMapping;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;

final class FbrDiV112Mapper
{
    /** @return array<string,mixed> */
    public function sale(FiscalConfiguration $config, Sale $sale): array
    {
        $sale->loadMissing(['lines.variant', 'register.store.merchant']);
        $profile = $config->profileData();
        $items = [];
        foreach ($sale->lines as $line) {
            $mapping = $this->mapping($config, $line->product_variant_id, $sale->business_date->toDateString());
            $items[] = $this->item($line, $mapping);
        }

        return ['invoiceType' => 'Sale Invoice', 'invoiceDate' => $sale->business_date->format('Y-m-d'), 'sellerNTNCNIC' => $config->tax_registration_number, 'sellerBusinessName' => $sale->register->store->merchant->name, 'sellerProvince' => $profile['seller_province'] ?? null, 'sellerAddress' => $profile['seller_address'] ?? null, 'buyerNTNCNIC' => data_get($sale->customer_snapshot, 'tax_registration_number', ''), 'buyerBusinessName' => data_get($sale->customer_snapshot, 'name', 'Walk-in customer'), 'buyerProvince' => $profile['default_buyer_province'] ?? $profile['seller_province'] ?? null, 'buyerAddress' => data_get($sale->customer_snapshot, 'address', ''), 'buyerRegistrationType' => data_get($sale->customer_snapshot, 'registration_type', 'Unregistered'), 'invoiceRefNo' => $sale->number, 'scenarioId' => $config->environment === 'sandbox' ? ($profile['scenario_id'] ?? null) : null, 'items' => $items];
    }

    /** @return array<string,mixed> */
    public function creditNote(FiscalConfiguration $config, SaleReturn $return): array
    {
        $return->loadMissing(['sale.lines.variant', 'lines.saleLine', 'register.store.merchant']);
        $profile = $config->profileData();
        $items = [];
        foreach ($return->lines as $line) {
            $source = $line->saleLine;
            $mapping = $this->mapping($config, $source->product_variant_id, $return->business_date->toDateString());
            $items[] = ['hsCode' => $mapping->hs_code, 'productDescription' => $source->product_name_snapshot, 'rate' => $mapping->rate_label, 'uoM' => $mapping->uom_code, 'quantity' => $line->quantity_uom, 'totalValues' => $line->gross, 'valueSalesExcludingST' => $line->net, 'salesTaxApplicable' => $line->tax, 'salesTaxWithheldAtSource' => '0.00', 'extraTax' => '0.00', 'furtherTax' => '0.00', 'sroScheduleNo' => $mapping->sro_schedule_no, 'fedPayable' => '0.00', 'discount' => $line->discount, 'saleType' => $mapping->sale_type, 'sroItemSerialNo' => $mapping->sro_item_serial_no];
        }

        return ['invoiceType' => 'Debit Note', 'invoiceDate' => $return->business_date->format('Y-m-d'), 'sellerNTNCNIC' => $config->tax_registration_number, 'sellerBusinessName' => $return->register->store->merchant->name, 'sellerProvince' => $profile['seller_province'] ?? null, 'sellerAddress' => $profile['seller_address'] ?? null, 'buyerRegistrationType' => 'Unregistered', 'invoiceRefNo' => $return->sale->number, 'scenarioId' => $config->environment === 'sandbox' ? ($profile['scenario_id'] ?? null) : null, 'items' => $items];
    }

    private function mapping(FiscalConfiguration $config, int $variantId, string $date): FiscalProductMapping
    {
        return FiscalProductMapping::query()->where('fiscal_configuration_id', $config->id)->where('product_variant_id', $variantId)->where('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $date))->latest('effective_from')->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function item(SaleLine $line, FiscalProductMapping $mapping): array
    {
        return ['hsCode' => $mapping->hs_code, 'productDescription' => $line->product_name_snapshot, 'rate' => $mapping->rate_label, 'uoM' => $mapping->uom_code, 'quantity' => $line->quantity_uom, 'totalValues' => $line->gross, 'valueSalesExcludingST' => $line->net, 'fixedNotifiedValueOrRetailPrice' => '0.00', 'salesTaxApplicable' => $line->tax, 'salesTaxWithheldAtSource' => '0.00', 'extraTax' => '0.00', 'furtherTax' => '0.00', 'sroScheduleNo' => $mapping->sro_schedule_no, 'fedPayable' => '0.00', 'discount' => $line->discount, 'saleType' => $mapping->sale_type, 'sroItemSerialNo' => $mapping->sro_item_serial_no];
    }
}
