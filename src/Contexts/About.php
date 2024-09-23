<?php

namespace App\Contexts;

readonly class About
{
    public function __construct(public ?string $bundleName, public ?string $bundleVersion, public ?string $bundleHumanReadableCopyright, public ?string $bundleShortVersion)
    {
    }
}