<?php

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\PropertyBlock;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

final class ClassDeclarationInjector
{
    /**
     * @param string $declaration
     * @param ArrayClass<PropertyBlock> $blocks
     * @return string
     */
    public function inject(string $declaration, ArrayClass $blocks): string
    {
        if ($blocks->isEmpty) {
            return $declaration;
        }
        $position = strpos($declaration, "{");
        if ($position === false) {
            return $declaration;
        }
        $injection = "\n    {$blocks->join("\n    ")}";
        $index = $position + 1;
        $declarationStart = substring_to_index($declaration, $index);
        $declarationEnd = substring_from_index($declaration, $index);
        return "$declarationStart$injection$declarationEnd";
    }
}
