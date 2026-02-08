<?php

namespace App\Bundles;

final readonly class BundleGenerationOptions
{
    public function __construct(public bool $withSecurity = false, public bool $withCORS = false, public bool $withJWT = false)
    {
    }
}
