<?php

namespace App\Logging;

use Monolog\Logger;

class CreateDatabaseLogger
{
    /** @param array<string, mixed> $config */
    public function __invoke(array $config): Logger
    {
        return new Logger('database', [new DatabaseLogHandler($config['level'] ?? 'info')]);
    }
}
