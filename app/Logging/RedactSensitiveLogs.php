<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\LogRecord;

class RedactSensitiveLogs
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'pin', 'device_key', 'bearer_token', 'authorization', 'cookie', 'set-cookie', 'cvv', 'pan', 'card_number', 'secret', 'token'];

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(context: $this->redact($record->context), extra: $this->redact($record->extra)));
    }

    /** @param array<mixed> $values
     * @return array<mixed>
     */
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::SENSITIVE_KEYS, true) || str_contains($normalized, 'credential')) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $values[$key] = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[REDACTED_CARD]', $value) ?? $value;
            }
        }

        return $values;
    }
}
