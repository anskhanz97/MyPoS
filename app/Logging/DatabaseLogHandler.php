<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $logger = app(OperationalLogger::class);
        $exception = $record->context['exception'] ?? null;
        $context = $record->context;
        if ($exception instanceof Throwable) {
            $context = ['exception_class' => $exception::class, 'exception_file' => basename($exception->getFile()), 'exception_line' => $exception->getLine()];
        }
        $logger->application($exception instanceof Throwable ? 'application.exception' : 'application.message', $exception instanceof Throwable ? 'An application error was reported. Check the exception location.' : $record->message, ['level' => strtolower($record->level->name), 'status' => $record->level->value >= 400 ? 'failed' : 'recorded', 'context' => $context]);
    }
}
