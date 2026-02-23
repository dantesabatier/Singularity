<?php

namespace App\FileWriters\ValueObjects;

use Override;
use Sabatier\Foundation\ObjectClass;

/**
 * Value object representing a generated magic method doc-block annotation
 */
final class GeneratedMethod extends ObjectClass
{
    #[Override]
    public string $description {
        get => " * @method $this->signature";
    }

    public function __construct(public readonly string $signature)
    {
    }
}
