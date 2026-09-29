<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property string $provider
 * @property string $category
 * @property string $mode
 * @property string $state
 * @property array<string, string>|null $config
 * @property array<string, string>|null $credentials
 * @property int $version
 * @property string|null $last_test_state
 * @property string|null $last_test_message
 * @property CarbonImmutable|null $last_tested_at
 */
#[Fillable(['merchant_id', 'provider', 'category', 'mode', 'state', 'config', 'credentials', 'version', 'last_test_state', 'last_test_message', 'last_tested_at'])]
class MerchantIntegration extends Model
{
    /** @use HasFactory<MerchantIntegrationFactory> */
    use HasBinaryPublicId, HasFactory;

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'version' => 'integer',
            'last_tested_at' => 'immutable_datetime',
        ];
    }
}
