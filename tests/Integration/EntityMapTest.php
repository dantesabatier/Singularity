<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\ModelBundle;
use App\Bundles\NewModelVersionTransaction;
use App\FileWriters\ModelFileWriter;
use App\Model\EntityMap;
use App\Model\EntityMapType;
use App\Model\Model;
use App\Model\ModelMap;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\CoreData\EntityMigrationPolicy;
use Sabatier\Foundation\FileManager;

final class MigrationPolicyStub extends EntityMigrationPolicy
{
}

final class EntityMapTest extends CoreDataTestCase
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
     * Freezes the project's model so a map has a real source version to be authored against.
     * @throws Exception
     */
    private function freeze(): void
    {
        $modelBundle = new ModelBundle($this->project->url ?? self::fail("Project has no bundle URL"), $this->project->name);
        FileManager::default()->createDirectory($modelBundle->modelFileURL->deletingLastPathComponent(), true);
        $transaction = new NewModelVersionTransaction($this->project);
        $transaction->execute();
        $this->modelMap->sourceModelURL = $modelBundle->urlForVersionNamed($transaction->frozenVersionName);
        $this->modelMap->sourceVersionName = $transaction->frozenVersionName;
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
    public function testAnEntityOnlyTheDestinationHasIsAdded(): void
    {
        $this->freeze();
        self::assertSame(EntityMapType::add, $this->makeEntityMap(null, "Book")->inferredType);
    }

    /**
     * @throws Exception
     */
    public function testAnEntityOnlyTheSourceHasIsRemoved(): void
    {
        $this->freeze();
        self::assertSame(EntityMapType::remove, $this->makeEntityMap("Book", null)->inferredType);
    }

    /**
     * @throws Exception
     */
    public function testAnEntityUnchangedBetweenTheTwoVersionsIsCopied(): void
    {
        $this->freeze();
        self::assertSame(EntityMapType::copy, $this->makeEntityMap("Book", "Book")->inferredType);
    }

    /**
     * @throws Exception
     */
    public function testAnEntityWhoseShapeChangedIsTransformed(): void
    {
        $sourceProject = $this->makeProject("Library");
        $sourceModel = $sourceProject->model ?? self::fail("Project has no model");
        $sourceEntity = $this->makeEntity($sourceModel, "Book");
        $sourceEntity->addPropertiesObject($this->makeAttribute("subtitle"));
        $this->context->save();
        $sourceModelURL = $this->temporaryURL("source", "mom");
        new ModelFileWriter($sourceModelURL, $sourceModel)->save();
        $this->modelMap->sourceModelURL = $sourceModelURL;
        self::assertSame(EntityMapType::transform, $this->makeEntityMap("Book", "Book")->inferredType);
    }

    /**
     * @throws Exception
     */
    public function testAMapIsMissingItsPolicyOnlyWhenItIsCustomWithoutOne(): void
    {
        $entityMap = $this->makeEntityMap("Book", "Book");
        $entityMap->type = EntityMapType::transform;
        self::assertFalse($entityMap->isMissingMigrationPolicy);
        $entityMap->type = EntityMapType::custom;
        self::assertTrue($entityMap->isMissingMigrationPolicy);
        $entityMap->entityMigrationPolicyClassName = MigrationPolicyStub::class;
        self::assertFalse($entityMap->isMissingMigrationPolicy);
    }

    /**
     * @throws Exception
     */
    public function testAPolicyNameThatIsNotAMigrationPolicyDoesNotCount(): void
    {
        $entityMap = $this->makeEntityMap("Book", "Book");
        $entityMap->type = EntityMapType::custom;
        $entityMap->entityMigrationPolicyClassName = self::class;
        self::assertNull($entityMap->migrationPolicyClassName);
        self::assertTrue($entityMap->isMissingMigrationPolicy);
    }

    /**
     * @throws Exception
     */
    public function testAPolicyNameThatDoesNotResolveToAClassDoesNotCount(): void
    {
        $entityMap = $this->makeEntityMap("Book", "Book");
        $entityMap->type = EntityMapType::custom;
        $entityMap->entityMigrationPolicyClassName = "App\Nowhere\NoSuchPolicy";
        self::assertNull($entityMap->migrationPolicyClassName);
        self::assertTrue($entityMap->isMissingMigrationPolicy);
    }

    /**
     * @throws Exception
     */
    public function testAnUnnamedMapIsNamedAfterTheTwoEntitiesItJoins(): void
    {
        self::assertSame("BookToPublication", $this->makeEntityMap("Book", "Publication")->mappingName);
    }

    /**
     * @throws Exception
     */
    public function testAnUnnamedMapWithOneSideIsNamedAfterThatSide(): void
    {
        self::assertSame("Book", $this->makeEntityMap("Book", null)->mappingName);
    }

    /**
     * @throws Exception
     */
    public function testATypedInNameIsKeptAsTheMappingName(): void
    {
        $entityMap = $this->makeEntityMap("Book", "Publication");
        $entityMap->name = "RetitleBooks";
        self::assertSame("RetitleBooks", $entityMap->mappingName);
    }
}
