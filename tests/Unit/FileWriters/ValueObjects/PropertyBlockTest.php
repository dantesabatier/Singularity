<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\ValueObjects;

use App\FileWriters\ValueObjects\PropertyBlock;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;

final class PropertyBlockTest extends TestCase
{
    public function testANonNullablePropertyRendersAKvcBackedAccessorPair(): void
    {
        $block = new PropertyBlock("name", "string", false);
        $expected = <<<'PHP'
        public string $name {
                get => $this->valueForKey(__PROPERTY__);
                set {
                    $this->setValueForKey($value, __PROPERTY__);
                }
            }
        PHP;
        self::assertSame($expected, $block->description);
    }

    public function testANullablePropertyPrefixesTheTypeWithAQuestionMark(): void
    {
        $block = new PropertyBlock("parent", "Entity", true);
        self::assertStringContainsString("public ?Entity \$parent {", $block->description);
    }

    public function testPhpAttributesArePrependedBeforeTheDeclaration(): void
    {
        $block = new PropertyBlock("owner", "User", false, new Set(["#[Owner]", "#[Writable]"]));
        $description = $block->description;
        self::assertStringContainsString("#[Owner]", $description);
        self::assertStringContainsString("#[Writable]", $description);
        self::assertStringStartsWith("#[", $description);
        self::assertLessThan(
            strpos($description, "public User \$owner"),
            strpos($description, "#[Owner]"),
            "attributes precede the property declaration"
        );
    }

    public function testWithoutAttributesTheBlockStartsWithThePublicKeyword(): void
    {
        $block = new PropertyBlock("age", "int", false);
        self::assertStringStartsWith("public int \$age {", $block->description);
    }
}
