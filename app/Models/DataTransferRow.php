<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $data_transfer_job_id @property int $row_number @property array<string,mixed> $payload @property list<string>|null $errors @property string $state */
#[Fillable(['data_transfer_job_id', 'row_number', 'payload', 'errors', 'state', 'result_reference'])]
class DataTransferRow extends Model
{
    /** @return array<string,mixed> */
    public function payloadData(): array
    {
        $value = $this->getAttribute('payload');
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

    protected function casts(): array
    {
        return ['payload' => 'array', 'errors' => 'array', 'row_number' => 'integer'];
    }
}
