<?php

namespace App\FileWriters\ValueObjects;

use Override;
use Sabatier\Foundation\ObjectClass;

final class GeneratedProperty extends ObjectClass
{
    #[Override]
    public string $description {
        get {
            $annotation = " * @property";
            if ($this->isReadOnly) {
                $annotation .= "-read";
            }
            $annotation .= " $this->type";
            if ($this->isNullable) {
                $annotation .= "|null";
            }
            return "$annotation \$$this->name";
        }
    }

    public function __construct(public readonly string $name, public readonly string $type, public readonly bool $isReadOnly = false, public readonly bool $isNullable = false)
    {
    }
}
