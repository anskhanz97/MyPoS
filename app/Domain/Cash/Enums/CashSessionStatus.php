<?php

namespace App\Domain\Cash\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case ClosePendingSync = 'close_pending_sync';
    case CountSubmitted = 'count_submitted';
    case VarianceReview = 'variance_review';
    case Closed = 'closed';
}
