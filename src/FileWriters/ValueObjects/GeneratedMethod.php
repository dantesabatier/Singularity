<?php

declare(strict_types=1);

namespace App\FileWriters\ValueObjects;

use Override;
use Sabatier\Foundation\ObjectClass;

final class GeneratedMethod extends ObjectClass
{
    #[Override]
    public string $description {
        get => " * @method $this->signature";
    }

    /**
     * @param string $signature The method signature to render in the `@method` docblock tag.
     */
    public function __construct(public readonly string $signature)
    {
    }
}
