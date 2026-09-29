<?php

namespace App\Console\Commands;

use App\Domain\Fiscal\FiscalService;
use Illuminate\Console\Command;

class ConsumeFiscalOutbox extends Command
{
    protected $signature = 'fiscal:consume-outbox {--limit=100}';

    protected $description = 'Queue applicable immutable fiscal documents from domain outbox events';

    public function handle(FiscalService $service): int
    {
        $count = $service->consumeOutbox((int) $this->option('limit'));
        $this->info("Consumed {$count} event(s).");

        return self::SUCCESS;
    }
}
