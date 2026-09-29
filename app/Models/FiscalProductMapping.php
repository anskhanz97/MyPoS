<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fiscal_configuration_id', 'product_variant_id', 'hs_code', 'uom_code', 'rate_label', 'sale_type', 'sro_schedule_no', 'sro_item_serial_no', 'effective_from', 'effective_until'])] class FiscalProductMapping extends Model
{
    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_until' => 'immutable_date'];
    }
}
