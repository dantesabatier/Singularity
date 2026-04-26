<?php

declare(strict_types=1);

namespace App\FileWriters\ValueObjects;

use Override;
use Sabatier\Foundation\ObjectClass;

final class GeneratedUseStatement extends ObjectClass
{
    #[Override]
    public string $description {
        get => "use $this->fullyQualifiedClassName;";
    }

    public function __construct(public readonly string $fullyQualifiedClassName)
    {
    }
}
