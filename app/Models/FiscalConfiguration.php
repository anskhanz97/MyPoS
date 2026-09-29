<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int $store_id @property string $authority @property string $adapter_version @property string $environment @property string $applicability @property string $state @property string|null $tax_registration_number @property string|null $outlet_reference @property string $outage_policy @property array<string,mixed>|null $profile @property array<string,mixed>|null $credentials @property string|null $evidence_hash @property int $version */
#[Fillable(['merchant_id', 'store_id', 'authority', 'adapter_version', 'environment', 'applicability', 'state', 'tax_registration_number', 'outlet_reference', 'outage_policy', 'profile', 'credentials', 'evidence_hash', 'sandbox_verified_at', 'production_approved_at', 'version'])]
class FiscalConfiguration extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<FiscalProductMapping,$this> */
    public function mappings(): HasMany
    {
        return $this->hasMany(FiscalProductMapping::class);
    }

    /** @return array<string,mixed> */
    public function profileData(): array
    {
        return $this->arrayAttribute('profile');
    }

    /** @return array<string,mixed> */
    public function credentialData(): array
    {
        return $this->arrayAttribute('credentials');
    }

    /** @return array<string,mixed> */
    private function arrayAttribute(string $key): array
    {
        $value = $this->getAttribute($key);
        if (! is_array($value)) {
            return [];
        } $result = [];
        foreach ($value as $name => $item) {
            if (is_string($name)) {
                $result[$name] = $item;
            }
        }

        return $result;
    }

    protected function casts(): array
    {
        return ['profile' => 'array', 'credentials' => 'encrypted:array', 'sandbox_verified_at' => 'immutable_datetime', 'production_approved_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
