<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\ValueObjects;

use App\FileWriters\ValueObjects\GeneratedMethod;
use PHPUnit\Framework\TestCase;

final class GeneratedMethodTest extends TestCase
{
    public function testDescriptionPrefixesTheSignatureWithTheMethodTag(): void
    {
        $method = new GeneratedMethod("void addChildrenObject(Child \$object)");
        self::assertSame(" * @method void addChildrenObject(Child \$object)", $method->description);
    }

    public function testPreservesTheSignatureVerbatim(): void
    {
        $signature = "Set<Child> intersectChildren(Set<Child> \$objects)";
        $method = new GeneratedMethod($signature);
        self::assertSame($signature, $method->signature);
    }
}
