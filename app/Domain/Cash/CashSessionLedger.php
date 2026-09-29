<?php

namespace App\Domain\Cash;

use App\Models\CashSession;
use Brick\Math\BigDecimal;

final class CashSessionLedger
{
    public function expected(CashSession $session): BigDecimal
    {
        return BigDecimal::of((string) $session->movements()->sum('signed_amount'))->toScale(2);
    }
}
