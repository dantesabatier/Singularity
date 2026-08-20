<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Model\EntityMapType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\EntityMappingType;

final class EntityMapTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{EntityMapType, EntityMappingType}>
     */
    public static function mappingTypes(): iterable
    {
        yield "undefined" => [EntityMapType::undefined, EntityMappingType::undefinedEntityMappingType];
        yield "custom" => [EntityMapType::custom, EntityMappingType::customEntityMappingType];
        yield "add" => [EntityMapType::add, EntityMappingType::addEntityMappingType];
        yield "remove" => [EntityMapType::remove, EntityMappingType::removeEntityMappingType];
        yield "copy" => [EntityMapType::copy, EntityMappingType::copyEntityMappingType];
        yield "transform" => [EntityMapType::transform, EntityMappingType::transformEntityMappingType];
    }

    #[DataProvider("mappingTypes")]
    public function testEveryCaseTranslatesToTheMappingTypeTheEngineReads(EntityMapType $type, EntityMappingType $expected): void
    {
        self::assertSame($expected, $type->entityMappingType());
    }

    public function testEveryCaseTranslatesWithoutLosingItsValue(): void
    {
        foreach (EntityMapType::cases() as $type) {
            self::assertSame($type->value, $type->entityMappingType()->value);
        }
    }

    public function testTheEditorDeclaresACaseForEveryMappingTypeTheEngineKnows(): void
    {
        $editorValues = array_map(static fn(EntityMapType $type): int => $type->value, EntityMapType::cases());
        $engineValues = array_map(static fn(EntityMappingType $type): int => $type->value, EntityMappingType::cases());
        self::assertSame($engineValues, $editorValues);
    }
}
