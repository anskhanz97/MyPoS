<?php

namespace App\Domain\Offline;

final class CanonicalPayload
{
    /** @param array<string, mixed> $payload */
    public function json(array $payload): string
    {
        return json_encode($this->sort($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        return hash('sha256', $this->json($payload));
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($this->sort(...), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        return $value;
    }
}
