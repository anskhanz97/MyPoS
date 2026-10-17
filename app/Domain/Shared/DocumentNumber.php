<?php

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class DocumentNumber
{
    public function next(int $merchantId, string $prefix, CarbonInterface|string|null $date = null): string
    {
        $day = CarbonImmutable::parse($date ?? now())->toDateString();

        return DB::transaction(function () use ($merchantId, $prefix, $day): string {
            $scope = ['merchant_id' => $merchantId, 'prefix' => $prefix, 'business_date' => $day];
            DB::table('document_sequences')->insertOrIgnore($scope + ['last_number' => 0]);
            $sequence = DB::table('document_sequences')->where($scope)->lockForUpdate()->first();
            $next = (int) $sequence->last_number + 1;
            DB::table('document_sequences')->where($scope)->update(['last_number' => $next]);

            return $prefix.'-'.str_replace('-', '', $day).'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        }, 5);
    }
}
