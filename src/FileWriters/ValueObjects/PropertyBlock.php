<?php

declare(strict_types=1);

namespace App\FileWriters\ValueObjects;

use Override;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;

final class PropertyBlock extends ObjectClass
{
    #[Override]
    public string $description {
        get {
            $code = "";
            if (!$this->phpAttributes->isEmpty) {
                $code .= $this->phpAttributes->join("\n    ");
                $code .= "\n    ";
            }
            $nullable = $this->isNullable ? "?" : "";
            $code .= "public $nullable$this->type \$$this->name {\n";
            $code .= "        get => \$this->valueForKey(__PROPERTY__);\n";
            $code .= "        set {\n";
            $code .= "            \$this->setValueForKey(\$value, __PROPERTY__);\n";
            $code .= "        }\n";
            return "$code    }";
        }
    }

    /**
     * @param Set<string> $phpAttributes
     */
    public function __construct(public readonly string $name, public readonly string $type, public readonly bool $isNullable, public readonly Set $phpAttributes = new Set())
    {
    }
}
