<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\ValueObjects;

use App\FileWriters\ValueObjects\GeneratedProperty;
use PHPUnit\Framework\TestCase;

final class GeneratedPropertyTest extends TestCase
{
    public function testAWritableNonNullablePropertyHasNoReadModifierOrNullUnion(): void
    {
        $property = new GeneratedProperty("name", "string");
        self::assertSame(" * @property string \$name", $property->description);
    }

    public function testAReadOnlyPropertyCarriesTheReadModifier(): void
    {
        $property = new GeneratedProperty("objectID", "ManagedObjectID", isReadOnly: true);
        self::assertSame(" * @property-read ManagedObjectID \$objectID", $property->description);
    }

    public function testANullablePropertyUnionsWithNull(): void
    {
        $property = new GeneratedProperty("parent", "Entity", isNullable: true);
        self::assertSame(" * @property Entity|null \$parent", $property->description);
    }

    public function testAReadOnlyNullablePropertyCombinesBothModifiers(): void
    {
        $property = new GeneratedProperty("owner", "User", isReadOnly: true, isNullable: true);
        self::assertSame(" * @property-read User|null \$owner", $property->description);
    }
}
