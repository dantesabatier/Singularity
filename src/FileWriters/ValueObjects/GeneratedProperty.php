<?php

declare(strict_types=1);

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

    /**
     * @param string $name The property name to render in the `@property` docblock tag.
     * @param string $type The property type to render in the `@property` docblock tag.
     * @param bool $isReadOnly Whether to render `@property-read` instead of `@property`.
     * @param bool $isNullable Whether to append `|null` to the rendered type.
     */
    public function __construct(public readonly string $name, public readonly string $type, public readonly bool $isReadOnly = false, public readonly bool $isNullable = false)
    {
    }
}
