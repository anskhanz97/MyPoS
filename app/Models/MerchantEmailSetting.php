<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'enabled', 'provider_label', 'from_address', 'from_name'])] class MerchantEmailSetting extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
