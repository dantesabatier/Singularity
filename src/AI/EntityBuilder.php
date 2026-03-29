<?php

namespace App\AI;

use App\Model\Entity;
use Override;
use Sabatier\CoreData\ManagedObject;

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
            $operation->model->addEntitiesObject($entity);
            $operation->model->entitiesByName[$operation->name] = $entity;
            return $this->object = $entity;
        }
    }

    public function __construct(CreateEntityPatchOperation $operation)
    {
        parent::__construct($operation);
    }
}
