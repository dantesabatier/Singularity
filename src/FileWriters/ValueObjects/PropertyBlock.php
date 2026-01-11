<?php

namespace App\FileWriters\ValueObjects;

use Sabatier\Foundation\Set;

/**
 * Value object representing a complete property block with attributes and getter/setter
 */
final readonly class PropertyBlock
{
    /**
     * @param Set<string> $phpAttributes
     */
    public function __construct(public string $name, public string $type, public bool $isNullable, public Set $phpAttributes = new Set())
    {
    }

    public function toCode(): string
    {
        $code = "";
        if (!$this->phpAttributes->isEmpty) {
            $code .= $this->phpAttributes->join("\n    ");
            $code .= "\n    ";
        }
        $nullable = $this->isNullable ? "|null" : "";
        $code .= "public $this->type$nullable \$$this->name {\n";
        $code .= "        get => \$this->valueForKey(__PROPERTY__);\n";
        $code .= "        set {\n";
        $code .= "            \$this->setValueForKey(\$value, __PROPERTY__);\n";
        $code .= "        }\n";
        return "$code    }";
    }
}
