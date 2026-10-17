<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StampBuild extends Command
{
    protected $signature = 'ops:stamp-build {--build= : Explicit deployment version}';

    protected $description = 'Stamp the application source build before config:cache and restarting workers';

    public function handle(): int
    {
        $version = (string) $this->option('build');
        if ($version === '') {
            $hash = hash_init('sha256');
            foreach (['app', 'bootstrap', 'config', 'routes', 'resources'] as $directory) {
                $files = File::allFiles(base_path($directory));
                usort($files, fn ($left, $right): int => strcmp($left->getRelativePathname(), $right->getRelativePathname()));
                foreach ($files as $file) {
                    if ($directory === 'bootstrap' && str_starts_with($file->getRelativePathname(), 'cache')) {
                        continue;
                    }
                    hash_update($hash, $directory.'/'.$file->getRelativePathname());
                    hash_update_file($hash, $file->getPathname());
                }
            }
            $version = 'source-'.substr(hash_final($hash), 0, 16);
        }
        if (! preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $version)) {
            $this->error('Use 1–100 letters, numbers, dots, underscores or hyphens for the build version.');

            return self::FAILURE;
        }
        File::replace(base_path('bootstrap/cache/log-build.php'), '<?php return '.var_export($version, true).';'.PHP_EOL);
        $this->info('Build stamped: '.$version.'. Rebuild cached configuration and restart queue workers to load it.');

        return self::SUCCESS;
    }
}
