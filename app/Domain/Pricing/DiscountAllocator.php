<?php

namespace App\Domain\Pricing;

use App\Domain\Shared\Math\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DiscountAllocator
{
    /**
     * @param  list<array{line_id: string, sequence: int, weight: BigDecimal}>  $lines
     * @return array<string, BigDecimal>
     */
    public function allocate(BigDecimal $amount, array $lines): array
    {
        if ($amount->isZero() || $lines === []) {
            return [];
        }
        $total = array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['weight']), BigDecimal::zero());
        if ($total->isZero()) {
            return [];
        }
        $cents = (int) (string) $amount->multipliedBy('100')->toScale(0, RoundingMode::HalfUp);
        $rows = [];
        $assigned = 0;
        foreach ($lines as $line) {
            $exact = BigDecimal::of($cents)->multipliedBy($line['weight'])->dividedBy($total, Decimal::CalculatorScale, RoundingMode::HalfUp);
            $base = (int) (string) $exact->toScale(0, RoundingMode::Floor);
            $assigned += $base;
            $rows[] = $line + ['cents' => $base, 'remainder' => $exact->minus($base)];
        }
        usort($rows, fn (array $a, array $b): int => ($b['remainder']->compareTo($a['remainder'])) ?: ([$a['sequence'], $a['line_id']] <=> [$b['sequence'], $b['line_id']]));
        for ($index = 0; $index < $cents - $assigned; $index++) {
            $rows[$index]['cents']++;
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['line_id']] = BigDecimal::of($row['cents'])->dividedBy('100', 2);
        }

        return $result;
    }
}
