<?php

namespace App\FileWriters\ValueObjects;
final readonly class GeneratedUseStatement
{
    public function __construct(public string $fullyQualifiedClassName) {
    }

    public function toString(): string
    {
        return "use $this->fullyQualifiedClassName;";
    }

    public function equals(self $other): bool
    {
        return $this->fullyQualifiedClassName === $other->fullyQualifiedClassName;
    }
}
