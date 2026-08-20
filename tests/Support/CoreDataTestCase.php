<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Model\Attribute;
use App\Model\Entity;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\URL;

abstract class CoreDataTestCase extends TemporaryDirectoryTestCase
{
    protected ManagedObjectContext $context;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $model = new ManagedObjectModel(URL::fileURL(dirname(__DIR__, 2) . "/Resources/Singularity.mom"));
        $coordinator = new PersistentStoreCoordinator($model);
        $coordinator->addPersistentStoreWithType(PersistentStoreType::xml, null, $this->temporaryURL("store", "xml"));
        $this->context = new ManagedObjectContext();
        $this->context->persistentStoreCoordinator = $coordinator;
    }

    /**
     * Returns a saved project holding an empty model, located inside this test's temporary directory.
     * @throws Exception
     */
    protected function makeProject(string $name = "Bookstore"): Project
    {
        $project = new Project($this->context);
        $project->name = $name;
        $project->creationDate = new Date();
        $project->url = $this->makeTemporaryDirectory($name);
        $model = new Model($this->context);
        $model->name = $name;
        $model->project = $project;
        $this->context->save();
        return $project;
    }

    /**
     * Returns an entity holding one string attribute, added to a project's model.
     * @throws Exception
     */
    protected function makeEntity(Model $model, string $name, string $attributeName = "name"): Entity
    {
        $entity = new Entity($this->context);
        $entity->name = $name;
        $model->addEntitiesObject($entity);
        $entity->addPropertiesObject($this->makeAttribute($attributeName));
        return $entity;
    }

    /**
     * Returns a string attribute, which every property the store holds needs spelled out in full.
     * @throws Exception
     */
    protected function makeAttribute(string $name): Attribute
    {
        $attribute = new Attribute($this->context);
        $attribute->name = $name;
        $attribute->type = AttributeType::string;
        $attribute->lazyDestinationEntityName = "";
        $attribute->lazyInverseRelationshipName = "";
        return $attribute;
    }
}
