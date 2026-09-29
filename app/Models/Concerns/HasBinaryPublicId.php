<?php

namespace App\Models\Concerns;

use App\Casts\BinaryUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

trait HasBinaryPublicId
{
    public static function bootHasBinaryPublicId(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('public_id') === null) {
                $model->setAttribute('public_id', (string) Str::uuid7());
            }
        });
    }

    public function initializeHasBinaryPublicId(): void
    {
        $this->mergeCasts(['public_id' => BinaryUuid::class]);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @param  Builder<Model>|Relation<Model, Model, mixed>  $query
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $routeField = $field ?? $this->getRouteKeyName();
        if ($routeField === 'public_id' && ! Uuid::isValid((string) $value)) {
            return $query->whereRaw('1 = 0');
        }

        $routeValue = $routeField === 'public_id' ? Uuid::fromString((string) $value)->getBytes() : $value;

        return $query->where($routeField, $routeValue);
    }
}
