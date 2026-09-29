<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('app:environment-check')]
#[Description('Validate required application configuration without displaying secrets')]
class ValidateEnvironment extends Command
{
    public function handle(): int
    {
        $required = ['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_URL', 'DB_CONNECTION', 'DB_DATABASE'];
        $missing = array_values(array_filter(
            $required,
            fn (string $key): bool => blank(config($this->configurationKey($key))),
        ));

        if ($missing !== []) {
            $this->components->error('Missing required environment values: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->components->info('Required application environment values are present.');

        return self::SUCCESS;
    }

    private function configurationKey(string $environmentKey): string
    {
        return match ($environmentKey) {
            'APP_NAME' => 'app.name',
            'APP_ENV' => 'app.env',
            'APP_KEY' => 'app.key',
            'APP_URL' => 'app.url',
            'DB_CONNECTION' => 'database.default',
            'DB_DATABASE' => 'database.connections.'.config('database.default').'.database',
            default => throw new InvalidArgumentException('Unknown required environment key: '.$environmentKey),
        };
    }
}
