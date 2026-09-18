<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\Generators;

use App\FileWriters\Generators\ClassDeclarationInjector;
use App\FileWriters\ValueObjects\PropertyBlock;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;

final class ClassDeclarationInjectorTest extends TestCase
{
    private function inject(string $declaration, PropertyBlock ...$blocks): string
    {
        return new ClassDeclarationInjector()->inject($declaration, new ArrayClass($blocks));
    }

    public function testNoBlocksLeavesTheDeclarationUntouched(): void
    {
        $declaration = "class Widget\n{\n}";
        self::assertSame($declaration, $this->inject($declaration));
    }

    public function testADeclarationWithoutAnOpeningBraceIsReturnedAsIs(): void
    {
        $declaration = "class Widget extends Base";
        self::assertSame($declaration, $this->inject($declaration, new PropertyBlock("name", "string", false)));
    }

    public function testBlocksAreInsertedRightAfterTheOpeningBrace(): void
    {
        $result = $this->inject("class Widget\n{\n}", new PropertyBlock("name", "string", false));
        self::assertStringStartsWith("class Widget\n{\n    public string \$name {", $result);
        self::assertStringEndsWith("}\n}", $result);
    }

    public function testMultipleBlocksAreJoinedWithIndentation(): void
    {
        $result = $this->inject(
            "class Widget\n{\n}",
            new PropertyBlock("name", "string", false),
            new PropertyBlock("age", "int", false)
        );
        self::assertStringContainsString("public string \$name {", $result);
        self::assertStringContainsString("public int \$age {", $result);
        self::assertLessThan(strpos($result, "\$age"), strpos($result, "\$name"), "blocks keep their order");
    }
}
