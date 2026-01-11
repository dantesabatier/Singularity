<?php

namespace App\FileWriters\ValueObjects;

/**
 * Value object representing a generated property doc-block annotation
 */
final readonly class GeneratedProperty
{
    public function __construct(public string $name, public string $type, public bool $isReadOnly = false, public bool $isNullable = false)
    {
    }

    public function toDocBlock(): string
    {
        $annotation = " * @property";
        if ($this->isReadOnly) {
            $annotation .= "-read";
        }
        $annotation .= " $this->type";
        if ($this->isNullable) {
            $annotation .= "|null";
        }
        $annotation .= " \$$this->name";
        return $annotation;
    }
}
