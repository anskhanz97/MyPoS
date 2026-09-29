<?php

namespace App\Domain\Platform\Enums;

enum EntitlementCapability: string
{
    case NewOperations = 'operations.create';
    case PendingOfflineSubmission = 'offline.pending.submit';
    case DataExport = 'data.export';
    case Support = 'support.access';
}
