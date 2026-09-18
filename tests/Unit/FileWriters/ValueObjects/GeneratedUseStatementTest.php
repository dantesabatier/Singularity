<?php

declare(strict_types=1);

namespace App\Tests\Unit\FileWriters\ValueObjects;

use App\FileWriters\ValueObjects\GeneratedUseStatement;
use PHPUnit\Framework\TestCase;

final class GeneratedUseStatementTest extends TestCase
{
    public function testDescriptionWrapsTheClassNameInAUseStatement(): void
    {
        $statement = new GeneratedUseStatement("Sabatier\\Foundation\\Set");
        self::assertSame("use Sabatier\\Foundation\\Set;", $statement->description);
    }

    public function testDescriptionIsTheStringRepresentation(): void
    {
        $statement = new GeneratedUseStatement("App\\Model\\Entity");
        self::assertSame($statement->description, (string)$statement);
    }

    public function testPreservesTheFullyQualifiedClassNameVerbatim(): void
    {
        $statement = new GeneratedUseStatement("App\\Model\\Entity");
        self::assertSame("App\\Model\\Entity", $statement->fullyQualifiedClassName);
    }
}
