<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\FileWriters\ModelFileWriter;
use App\Model\EntityMap;
use App\Model\Model;
use App\Model\ModelMap;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\CoreData\EntityMapping;
use Sabatier\CoreData\EntityMappingType;
use Sabatier\CoreData\MappingModel;
use Sabatier\CoreData\PropertyMapping;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\AutomaticallyDeleteMappingModelFilesPreferencesKey;

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
        $project->addModelMapsObject($this->modelMap);
        $this->context->save();
    }

    #[Override]
    protected function tearDown(): void
    {
        UserDefaults::standard()->setBool(false, AutomaticallyDeleteMappingModelFilesPreferencesKey);
        parent::tearDown();
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
        $valid->type = EntityMappingType::copyEntityMappingType;
        $invalid = $this->makeEntityMap("Author", "Author", 1);
        $invalid->type = EntityMappingType::customEntityMappingType;
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
    public function testMaterializingAMappingModelPreservesEntityAndPropertyUserInfo(): void
    {
        $propertyMapping = new PropertyMapping("title");
        $propertyMapping->userInfo = new Dictionary(["scope" => "property"]);
        $relationshipMapping = new PropertyMapping("author");
        $relationshipMapping->userInfo = new Dictionary(["scope" => "relationship"]);
        $entityMapping = new EntityMapping("BookToBook");
        $entityMapping->sourceEntityName = "Book";
        $entityMapping->destinationEntityName = "Book";
        $entityMapping->mappingType = EntityMappingType::copyEntityMappingType;
        $entityMapping->userInfo = new Dictionary(["scope" => "entity"]);
        $entityMapping->attributeMappings = new ArrayClass([$propertyMapping]);
        $entityMapping->relationshipMappings = new ArrayClass([$relationshipMapping]);
        $mappingModel = new MappingModel();
        $mappingModel->entityMappings = new ArrayClass([$entityMapping]);

        $this->modelMap->mappingModel = $mappingModel;

        $entityMap = $this->modelMap->entityMaps->first ?? self::fail("Mapping model has no entity map");
        $propertyMap = $entityMap->attributes->first ?? self::fail("Entity map has no attribute map");
        $relationshipMap = $entityMap->relationships->first ?? self::fail("Entity map has no relationship map");
        self::assertSame("entity", $entityMap->userInfo?->valueForKey("scope"));
        self::assertSame("property", $propertyMap->userInfo?->valueForKey("scope"));
        self::assertSame("relationship", $relationshipMap->userInfo?->valueForKey("scope"));
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

    /**
     * @throws Exception
     */
    public function testANameCannotContainWhitespace(): void
    {
        $this->modelMap->name = " Bookstore 2 To Bookstore ";
        $this->context->save();
        self::assertSame("Bookstore2ToBookstore", $this->modelMap->name);
    }

    /**
     * @throws Exception
     */
    public function testDeletingAMapKeepsItsFileWhenAutomaticDeletionIsDisabled(): void
    {
        $url = $this->modelMap->mappingModelURL ?? self::fail("Mapping model has no file URL");
        FileManager::default()->createDirectory($url->deletingLastPathComponent(), true);
        FileManager::default()->createFile($url->path, "mapping model");
        $this->context->delete($this->modelMap);
        $this->context->save();
        self::assertTrue(FileManager::default()->fileExists($url->path));
    }

    /**
     * @throws Exception
     */
    public function testDeletingAMapDeletesItsFileWhenAutomaticDeletionIsEnabled(): void
    {
        UserDefaults::standard()->setBool(true, AutomaticallyDeleteMappingModelFilesPreferencesKey);
        $url = $this->modelMap->mappingModelURL ?? self::fail("Mapping model has no file URL");
        FileManager::default()->createDirectory($url->deletingLastPathComponent(), true);
        FileManager::default()->createFile($url->path, "mapping model");
        $this->context->delete($this->modelMap);
        $this->context->save();
        self::assertFalse(FileManager::default()->fileExists($url->path));
    }

}
