<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $public_id @property int $merchant_id @property int|null $store_id @property int $actor_user_id @property string $direction @property string $resource @property string $state @property string $disk @property string|null $source_path @property string|null $artifact_path @property array<string,mixed>|null $options @property string|null $commit_key @property int $invalid_rows @property CarbonImmutable|null $expires_at */
#[Fillable(['merchant_id', 'store_id', 'actor_user_id', 'direction', 'resource', 'state', 'schema_version', 'original_name', 'disk', 'source_path', 'source_hash', 'options', 'total_rows', 'valid_rows', 'invalid_rows', 'commit_key', 'committed_at', 'artifact_path', 'artifact_hash', 'expires_at', 'failure_message'])]
class DataTransferJob extends Model
{
    use HasBinaryPublicId;

    /** @return HasMany<DataTransferRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(DataTransferRow::class);
    }

    /** @return array<string,mixed> */
    public function optionsData(): array
    {
        $value = $this->getAttribute('options');
        if (! is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    public function artifactIsCurrent(): bool
    {
        $raw = $this->getRawOriginal('expires_at');

        return is_string($raw) && $this->asDateTime($raw)->isFuture();
    }

    protected function casts(): array
    {
        return ['options' => 'array', 'schema_version' => 'integer', 'total_rows' => 'integer', 'valid_rows' => 'integer', 'invalid_rows' => 'integer', 'committed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
