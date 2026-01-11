<?php

namespace App\FileWriters\ValueObjects;

use Sabatier\Foundation\ObjectClass;

final class GeneratedUseStatement extends ObjectClass
{
    public string $description {
        get => "use $this->fullyQualifiedClassName;";
    }

    public function __construct(public readonly string $fullyQualifiedClassName)
    {
    }
}
