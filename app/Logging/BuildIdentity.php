<?php

namespace App\Logging;

class BuildIdentity
{
    public readonly string $version;

    public function __construct()
    {
        $this->version = mb_substr((string) config('observability.build_version'), 0, 100);
    }
}
