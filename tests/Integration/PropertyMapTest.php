<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Model\EntityMap;
use App\Model\ModelMap;
use App\Model\PropertyMap;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;

final class PropertyMapTest extends CoreDataTestCase
{
    private EntityMap $entityMap;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $project = $this->makeProject();
        $model = $project->model ?? self::fail("Project has no model");
        $modelMap = new ModelMap($this->context);
        $modelMap->name = "BookstoreToBookstore 2";
        $modelMap->model = $model;
        $this->entityMap = new EntityMap($this->context);
        $this->entityMap->sourceEntityName = "Book";
        $this->entityMap->destinationEntityName = "Book";
        $modelMap->addEntityMapsObject($this->entityMap);
        $this->context->save();
    }

    /**
     * @throws Exception
     */
    private function makePropertyMap(string $name, ?string $valueExpressionFormat = null): PropertyMap
    {
        $propertyMap = new PropertyMap($this->context);
        $propertyMap->name = $name;
        $propertyMap->valueExpressionFormat = $valueExpressionFormat;
        return $propertyMap;
    }

    /**
     * @throws Exception
     */
    public function testAnAttributeMapBelongsToTheEntityMapHoldingIt(): void
    {
        $propertyMap = $this->makePropertyMap("title");
        $this->entityMap->addAttributesObject($propertyMap);
        $this->context->save();
        self::assertSame($this->entityMap, $propertyMap->entityMap);
    }

    /**
     * @throws Exception
     */
    public function testARelationshipMapBelongsToTheEntityMapHoldingIt(): void
    {
        $propertyMap = $this->makePropertyMap("author");
        $this->entityMap->addRelationshipsObject($propertyMap);
        $this->context->save();
        self::assertSame($this->entityMap, $propertyMap->entityMap);
    }

    /**
     * @throws Exception
     */
    public function testAPropertyMapInNeitherCollectionBelongsToNoEntityMap(): void
    {
        $propertyMap = $this->makePropertyMap("orphan");
        $this->context->save();
        self::assertNull($propertyMap->entityMap);
    }

    /**
     * @throws Exception
     */
    public function testAMapWithoutAFormatProducesNoValueExpression(): void
    {
        self::assertNull($this->makePropertyMap("title")->valueExpression);
    }

    /**
     * @throws Exception
     */
    public function testAnEmptyFormatIsStoredAsNoFormatAtAll(): void
    {
        $propertyMap = $this->makePropertyMap("title", "");
        self::assertNull($propertyMap->valueExpressionFormat);
        self::assertNull($propertyMap->valueExpression);
    }

    /**
     * @throws Exception
     */
    public function testAFormatIsParsedIntoTheExpressionTheEngineEvaluates(): void
    {
        $propertyMap = $this->makePropertyMap("title", '$source.title');
        self::assertNotNull($propertyMap->valueExpression);
    }

    /**
     * @throws Exception
     */
    public function testTheMappingTheEngineReadsCarriesTheDestinationPropertyName(): void
    {
        $propertyMap = $this->makePropertyMap("title", '$source.title');
        self::assertSame("title", $propertyMap->propertyMapping->name);
        self::assertNotNull($propertyMap->propertyMapping->valueExpression);
    }

    /**
     * @throws Exception
     */
    public function testAMappingWithoutAFormatCarriesNoExpression(): void
    {
        self::assertNull($this->makePropertyMap("title")->propertyMapping->valueExpression);
    }

    /**
     * @throws Exception
     */
    public function testSavingTrimsTheNameAndTheValueExpressionFormat(): void
    {
        $propertyMap = $this->makePropertyMap("  title  ", '  $source.title  ');
        $this->entityMap->addAttributesObject($propertyMap);
        $this->context->save();
        self::assertSame("title", $propertyMap->name);
        self::assertSame('$source.title', $propertyMap->valueExpressionFormat);
    }
}
