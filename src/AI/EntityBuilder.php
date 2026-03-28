<?php

namespace App\AI;

use App\Model\Entity;
use App\Model\Model;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;

final class EntityBuilder extends PatchObjectBuilder
{
    #[Override]
    private(set) ManagedObject $object {
        get {
            if (isset($this->object)) {
                return $this->object;
            }
            /** @var CreateEntityPatchOperation $operation */
            $operation = $this->operation;
            $entity = new Entity($this->managedObjectContext);
            $entity->name = $operation->name;
            $this->model->addEntitiesObject($entity);
            $this->model->entitiesByName[$operation->name] = $entity;
            return $this->object = $entity;
        }
    }

    public function __construct(CreateEntityPatchOperation $operation, ManagedObjectContext $managedObjectContext, Model $model)
    {
        parent::__construct($operation, $managedObjectContext, $model);
    }
}
