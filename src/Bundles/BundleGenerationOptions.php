<?php

declare(strict_types=1);

namespace App\Bundles;

final readonly class BundleGenerationOptions
{
    /**
     * @param bool $withSecurity Whether the generated bundle includes the security scaffolding (access control, field-level security).
     * @param bool $withCORS Whether the generated bundle includes CORS configuration.
     * @param bool $withJWT Whether the generated bundle is configured for JWT authentication instead of session mode.
     */
    public function __construct(public bool $withSecurity = false, public bool $withCORS = false, public bool $withJWT = false)
    {
    }
}
