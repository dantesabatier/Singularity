<?php

declare(strict_types=1);

namespace App\Bundles;

final readonly class DatabaseExportationOptions
{
    public function __construct(public bool $withData = false, public bool $withComments = false)
    {
    }
}
