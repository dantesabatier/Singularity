<?php

declare(strict_types=1);

namespace App\FileWriters\Parsers;

use Exception;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

final class ExistingClassParser
{
    /**
     * @return array{uses: Set<string>, properties: Set<string>, classProperties: Set<string>, methods: Set<string>, declaration: string|null}
     * @throws Exception
     */
    public function parse(URL $fileURL): array
    {
        /** @var Set<string> $uses */
        $uses = new Set();
        /** @var Set<string> $properties */
        $properties = new Set();
        /** @var Set<string> $classProperties */
        $classProperties = new Set();
        /** @var Set<string> $methods */
        $methods = new Set();
        $declaration = null;
        $path = $fileURL->path;
        if (!FileManager::default()->fileExists($path) || !($contents = FileManager::default()->contents($path))) {
            return $this->assemble($uses, $properties, $classProperties, $methods, $declaration);
        }
        $index = strpos($contents, "class");
        if ($index === false) {
            return $this->assemble($uses, $properties, $classProperties, $methods, $declaration);
        }
        $beforeClass = substring_to_index($contents, $index);
        if ($lines = preg_split("/\n/", $beforeClass, -1, PREG_SPLIT_NO_EMPTY)) {
            array_filter($lines, fn(string $e): bool => str_starts_with($e, "use"))
                |> (fn(array $x): array => array_map(rtrim(...), $x))
                |> $uses->formUnion(...);
            array_filter($lines, fn(string $e): bool => str_starts_with($e, " * @property"))
                |> (fn(array $x): array => array_map(rtrim(...), $x))
                |> $properties->formUnion(...);
        }
        $declaration = substring_from_index($contents, $index);
        $propRegex = "/(?P<slot>(?:public|protected|private|var|readonly|static)\\s+[^;{]*?\\\$[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)\\s*[;={]/s";
        if (preg_match_all($propRegex, $declaration, $matches)) {
            $classProperties->formUnion(array_map(trim(...), $matches["slot"]));
        }
        $methodRegex = "/(?P<slot>(?:(?:public|protected|private|static|final|abstract)\\s+)*function\\s+[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*\\s*\\(.*?\\)(?:\\s*:\\s*[a-zA-Z0-9_|\\?]+)?)\\s*[;{]/s";
        if (preg_match_all($methodRegex, $declaration, $matches)) {
            $methods->formUnion(array_map(trim(...), $matches["slot"]));
        }
        return $this->assemble($uses, $properties, $classProperties, $methods, $declaration);
    }

    /**
     * @param Set<string> $uses
     * @param Set<string> $properties
     * @param Set<string> $classProperties
     * @param Set<string> $methods
     * @param string|null $declaration
     * @return array{uses: Set<string>, properties: Set<string>, classProperties: Set<string>, methods: Set<string>, declaration: string|null}
     */
    private function assemble(Set $uses, Set $properties, Set $classProperties, Set $methods, ?string $declaration): array
    {
        return [
            "uses" => $uses,
            "properties" => $properties,
            "classProperties" => $classProperties,
            "methods" => $methods,
            "declaration" => $declaration
        ];
    }
}
