<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\ModelBundle;
use App\FileWriters\ModelFileWriter;
use App\FileWriters\ModelMapFileWriter;
use App\Model\EntityMap;
use App\Model\Model;
use App\Model\ModelMap;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\CoreData\MappingModel;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class ModelMapFileWriterTest extends CoreDataTestCase
{
    private Project $project;
    private Model $model;
    private ModelMap $modelMap;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->model = $this->project->model ?? self::fail("Project has no model");
        $this->makeEntity($this->model, "Book");
        $this->modelMap = new ModelMap($this->context);
        $this->modelMap->name = "BookstoreToBookstore 2";
        $this->modelMap->model = $this->model;
        $this->context->save();
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
    private function makeEntityMap(?string $sourceEntityName, ?string $destinationEntityName): EntityMap
    {
        $entityMap = new EntityMap($this->context);
        $entityMap->sourceEntityName = $sourceEntityName;
        $entityMap->destinationEntityName = $destinationEntityName;
        $this->modelMap->addEntityMapsObject($entityMap);
        return $entityMap;
    }

    /**
     * @throws Exception
     */
    public function testTheWrittenFileReadsBackAsAMappingModel(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book");
        $url = $this->temporaryURL("BookstoreToBookstore 2", "cdm");
        new ModelMapFileWriter($url, $this->modelMap)->save();
        self::assertTrue(FileManager::default()->fileExists($url->path));
        self::assertInstanceOf(MappingModel::class, new MappingModel($url));
    }

    /**
     * @throws Exception
     */
    public function testTheWrittenMappingModelCarriesTheEntityMappingsTheMapSpellsOut(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book");
        $url = $this->temporaryURL("BookstoreToBookstore 2", "cdm");
        new ModelMapFileWriter($url, $this->modelMap)->save();
        $names = new MappingModel($url)->entityMappings->map(fn($entityMapping): string => $entityMapping->name)->array;
        self::assertSame(["BookToBook"], $names);
    }

    /**
     * @throws Exception
     */
    public function testTheWrittenMappingModelCarriesTheVersionHashesThatLocateIt(): void
    {
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book");
        $url = $this->temporaryURL("BookstoreToBookstore 2", "cdm");
        new ModelMapFileWriter($url, $this->modelMap)->save();
        $mappingModel = new MappingModel($url);
        self::assertNotNull($mappingModel->sourceEntityVersionHashesByName->valueForKey("Book"));
        self::assertNotNull($mappingModel->destinationEntityVersionHashesByName->valueForKey("Book"));
    }

    /**
     * @throws Exception
     */
    public function testAMappingModelIsWrittenBesideThePackageWhereTheBundleCanEnumerateIt(): void
    {
        $modelBundle = new ModelBundle($this->project->url ?? self::fail("Project has no bundle URL"), $this->project->name);
        FileManager::default()->createDirectory($modelBundle->modelFileURL->deletingLastPathComponent(), true);
        $this->modelMap->sourceModelURL = $this->writeSourceModel();
        $this->makeEntityMap("Book", "Book");
        $url = $modelBundle->urlForMappingModelNamed($this->modelMap->name);
        new ModelMapFileWriter($url, $this->modelMap)->save();
        self::assertTrue(FileManager::default()->fileExists($url->path));
        self::assertSame("cdm", $url->pathExtension);
    }
}
