<?php

namespace App\FileWriters\ValueObjects;

/**
 * Value object representing a generated magic method doc-block annotation
 */
final readonly class GeneratedMethod
{
    public function __construct(public string $signature)
    {
    }

    public function toDocBlock(): string
    {
        return " * @method $this->signature";
    }
}
