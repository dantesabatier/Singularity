<?php

declare(strict_types=1);

namespace App\Tests\Unit\LLM;

use App\LLM\Model;
use App\LLM\ModelBuilder;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;

final class ModelTest extends TestCase
{
    public function testDictionaryRepresentationCarriesEveryField(): void
    {
        $model = new Model("Opus 4.8", "claude-opus-4-8");
        $dictionary = $model->dictionaryRepresentation;
        self::assertSame("Opus 4.8", $dictionary["name"]);
        self::assertSame("claude-opus-4-8", $dictionary["identifier"]);
    }

    public function testBuildReconstructsAnEquivalentModel(): void
    {
        $original = new Model("Sonnet 4.6", "claude-sonnet-4-6");
        $rebuilt = ModelBuilder::build($original->dictionaryRepresentation);
        self::assertSame($original->name, $rebuilt->name);
        self::assertSame($original->identifier, $rebuilt->identifier);
    }

    public function testBuildFallsBackToEmptyStringsForMissingKeys(): void
    {
        $model = ModelBuilder::build(new Dictionary());
        self::assertSame("", $model->name);
        self::assertSame("", $model->identifier);
    }
}
