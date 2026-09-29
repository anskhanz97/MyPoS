<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/** @implements CastsAttributes<string, string> */
class BinaryUuid implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Uuid::fromBytes($value)->toString();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Uuid::fromString((string) $value)->getBytes();
    }
}
