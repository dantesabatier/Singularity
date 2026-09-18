<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\Parsers;

use App\FileWriters\Parsers\ExistingClassParser;
use App\Tests\Support\TemporaryDirectoryTestCase;
use Exception;
use Sabatier\Foundation\FileManager;

final class ExistingClassParserTest extends TemporaryDirectoryTestCase
{
    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function parse(string $contents): array
    {
        $url = $this->temporaryURL("Widget", "php");
        FileManager::default()->createFile($url->path, $contents);
        return new ExistingClassParser()->parse($url);
    }

    /**
     * @throws Exception
     */
    public function testAMissingFileYieldsEmptySetsAndNoDeclaration(): void
    {
        $result = new ExistingClassParser()->parse($this->temporaryURL("Missing", "php"));
        self::assertTrue($result["uses"]->isEmpty);
        self::assertTrue($result["properties"]->isEmpty);
        self::assertTrue($result["classProperties"]->isEmpty);
        self::assertTrue($result["methods"]->isEmpty);
        self::assertNull($result["declaration"]);
    }

    /**
     * @throws Exception
     */
    public function testCollectsUseStatementsAndPropertyAnnotationsBeforeTheClass(): void
    {
        $contents = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Model;

        use Sabatier\CoreData\ManagedObject;
        use Sabatier\Foundation\Set;

        /**
         * @property string $name
         * @property-read int $count
         */
        final class Widget extends ManagedObject
        {
        }
        PHP;
        $result = $this->parse($contents);

        self::assertTrue($result["uses"]->containsElement("use Sabatier\\CoreData\\ManagedObject;"));
        self::assertTrue($result["uses"]->containsElement("use Sabatier\\Foundation\\Set;"));
        self::assertTrue($result["properties"]->containsElement(" * @property string \$name"));
        self::assertTrue($result["properties"]->containsElement(" * @property-read int \$count"));
    }

    /**
     * @throws Exception
     */
    public function testCapturesTheDeclarationFromTheClassKeywordOnwards(): void
    {
        $contents = <<<'PHP'
        <?php

        namespace App\Model;

        final class Widget
        {
        }
        PHP;
        $result = $this->parse($contents);

        self::assertIsString($result["declaration"]);
        self::assertStringStartsWith("class Widget", $result["declaration"]);
    }

    /**
     * @throws Exception
     */
    public function testExtractsClassPropertiesAndMethodSignatures(): void
    {
        $contents = <<<'PHP'
        <?php

        namespace App\Model;

        final class Widget
        {
            public string $title;
            private int $count = 0;

            public function greet(string $who): string
            {
                return "hi";
            }

            protected function reset(): void
            {
            }
        }
        PHP;
        $result = $this->parse($contents);

        self::assertTrue($result["classProperties"]->contains(fn(string $slot): bool => str_contains($slot, "\$title")));
        self::assertTrue($result["classProperties"]->contains(fn(string $slot): bool => str_contains($slot, "\$count")));
        self::assertTrue($result["methods"]->contains(fn(string $slot): bool => str_contains($slot, "function greet")));
        self::assertTrue($result["methods"]->contains(fn(string $slot): bool => str_contains($slot, "function reset")));
    }
}
