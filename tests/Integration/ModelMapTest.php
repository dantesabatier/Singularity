<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\ModelFileWriter;
use App\Model\EntityMap;
use App\Model\EntityMapType;
use App\Model\Model;
use App\Model\ModelMap;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\Foundation\URL;

final class ModelMapTest extends CoreDataTestCase
{
    private Model $model;
    private ModelMap $modelMap;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $project = $this->makeProject();
        $this->model = $project->model ?? self::fail("Project has no model");
        $this->makeEntity($this->model, "Book");
        $this->makeEntity($this->model, "Author");
        $this->modelMap = new ModelMap($this->context);
        $this->modelMap->name = "BookstoreToBookstore 2";
        $this->modelMap->model = $this->model;
        $this->context->save();
    }

    /**
     * @throws Exception
     */
    private function makeEntityMap(?string $sourceEntityName, ?string $destinationEntityName, int $position = 0): EntityMap
    {
        $entityMap = new EntityMap($this->context);
        $entityMap->sourceEntityName = $sourceEntityName;
        $entityMap->destinationEntityName = $destinationEntityName;
        $entityMap->position = $position;
        $this->modelMap->addEntityMapsObject($entityMap);
        return $entityMap;
    }

    /**
     * Writes the project's model to a file the map can start from.
     * @throws Exception
     */
    private function writeSourceModel(): URL
    {
        $url = $this->temporaryURL("source", "mom");
        new ModelFileWriter($url, $this->model)->save();
        return $url;
    }

    /**
     * @throws Exception
     */
    public function testTheEntityMapsAreOrderedByThePositionTheMigrationProcessesThemIn(): void
    {
        $this->makeEntityMap("Author", "Author", 1);
        $this->makeEntityMap("Book", "Book", 0);
        $this->context->save();
        $names = $this->modelMap->orderedEntityMaps->map(fn(EntityMap $entityMap): string => $entityMap->mappingName)->array;
        self::assertSame(["BookToBook", "AuthorToAuthor"], $names);
    }

    /**
     * @throws Exception
     */
    public function testOnlyTheCustomMapsWithoutAPolicyAreReportedAsInvalid(): void
    {
        $valid = $this->makeEntityMap("Book", "Book", 0);
        $valid->type = EntityMapType::copy;
        $invalid = $this->makeEntityMap("Author", "Author", 1);
        $invalid->type = EntityMapType::custom;
        $this->context->save();
        $names = $this->modelMap->invalidEntityMaps->map(fn(EntityMap $entityMap): string => $entityMap->mappingName)->array;
        self::assertSame(["AuthorToAuthor"], $names);
    }

    /**
     * @throws Exception
     */
    public function testAMapWithoutARecordedSourceHasNoSourceModel(): void
    {
        self::assertNull($this->modelMap->sourceModel);
    }

    /**
     * @throws Exception
     */
    public function testTheSourceModelIsLoadedFromTheFileTheMapRecorded(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $sourceModel = $this->modelMap->sourceModel;
        self::assertNotNull($sourceModel);
        self::assertNotNull($sourceModel->entitiesByName["Book"]);
    }

    /**
     * @throws Exception
     */
    public function testTheMappingModelCarriesTheTwoVersionsTheMapWasAuthoredAgainst(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $mappingModel = $this->modelMap->mappingModel;
        self::assertSame($this->modelMap->sourceModel, $mappingModel->sourceModel);
        self::assertSame($this->model->managedObjectModel, $mappingModel->destinationModel);
    }

    /**
     * @throws Exception
     */
    public function testTheMappingModelCarriesOneMappingPerEntityMap(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book", 0);
        $this->makeEntityMap("Author", "Author", 1);
        $this->context->save();
        $names = $this->modelMap->mappingModel->entityMappings->map(fn($entityMapping): string => $entityMapping->name)->array;
        self::assertSame(["BookToBook", "AuthorToAuthor"], $names);
    }

    /**
     * @throws Exception
     */
    public function testTheMappingModelRecordsTheVersionHashOfEverySideEachEntityMapNames(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book", 0);
        $this->makeEntityMap("Author", "Author", 1);
        $mappingModel = $this->modelMap->mappingModel;
        foreach (["Book", "Author"] as $entityName) {
            self::assertNotNull($mappingModel->sourceEntityVersionHashesByName->valueForKey($entityName));
            self::assertNotNull($mappingModel->destinationEntityVersionHashesByName->valueForKey($entityName));
        }
    }

    /**
     * @throws Exception
     */
    public function testAnAddedEntityContributesNoSourceVersionHash(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap(null, "Book", 0);
        $mappingModel = $this->modelMap->mappingModel;
        self::assertSame(0, $mappingModel->sourceEntityVersionHashesByName->count);
        self::assertNotNull($mappingModel->destinationEntityVersionHashesByName->valueForKey("Book"));
    }

    /**
     * @throws Exception
     */
    public function testARemovedEntityContributesNoDestinationVersionHash(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", null, 0);
        $mappingModel = $this->modelMap->mappingModel;
        self::assertNotNull($mappingModel->sourceEntityVersionHashesByName->valueForKey("Book"));
        self::assertSame(0, $mappingModel->destinationEntityVersionHashesByName->count);
    }

}
