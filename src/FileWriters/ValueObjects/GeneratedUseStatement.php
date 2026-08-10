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

    /**
     * @param string $fullyQualifiedClassName The fully-qualified class name to render in the `use` statement.
     */
    public function __construct(public readonly string $fullyQualifiedClassName)
    {
    }
}
