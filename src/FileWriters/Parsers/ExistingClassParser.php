<?php

namespace App\FileWriters\Parsers;

use Exception;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/**
 * Parses existing class files to extract uses, properties, and declaration
 */
final class ExistingClassParser
{
    /**
     * @return array{uses: Set<string>, properties: Set<string>, declaration: string}
     * @throws Exception
     */
    public function parse(URL $fileURL): array
    {
        /** @var Set<string> $uses */
        $uses = new Set();
        /** @var Set<string> $properties */
        $properties = new Set();
        $declaration = "";
        $path = $fileURL->path;
        if (!FileManager::default()->fileExists($path)) {
            return ["uses" => $uses, "properties" => $properties, "declaration" => $declaration];
        }
        $contents = FileManager::default()->contents($path);
        if (!$contents) {
            return ["uses" => $uses, "properties" => $properties, "declaration" => $declaration];
        }

        $index = strpos($contents, "class");
        if ($index === false) {
            return ["uses" => $uses, "properties" => $properties, "declaration" => $declaration];
        }
        $beforeClass = substring_to_index($contents, $index);
        if ($lines = preg_split(sprintf("/%s/", preg_quote("\n", "/")), $beforeClass, -1, PREG_SPLIT_NO_EMPTY)) {
            $uses->formUnion(array_map(rtrim(...), array_filter($lines, fn(string $e): bool => str_starts_with($e, "use"))));
            $properties->formUnion(array_map(rtrim(...), array_filter($lines, fn(string $e): bool => str_starts_with($e, " * @property"))));
        }
        $declaration = substring_from_index($contents, $index);
        return ["uses" => $uses, "properties" => $properties, "declaration" => $declaration];
    }
}
