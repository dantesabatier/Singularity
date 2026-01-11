<?php

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\PropertyBlock;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/**
 * Injects property blocks into class declarations
 */
final class ClassDeclarationInjector
{
    /**
     * @param ArrayClass<PropertyBlock> $propertyBlocks
     */
    public function inject(string $declaration, ArrayClass $propertyBlocks): string
    {
        if ($propertyBlocks->isEmpty) {
            return $declaration;
        }
        $position = strpos($declaration, "{");
        if ($position === false) {
            return $declaration;
        }
        $index = $position + 1;
        $declarationStart = substring_to_index($declaration, $index);
        $declarationEnd = substring_from_index($declaration, $index);
        $parts = $propertyBlocks->map(fn(mixed $block): string => $block->toCode());
        $injection = "\n    {$parts->join("\n    ")}";
        return $declarationStart . $injection . $declarationEnd;
    }
}
