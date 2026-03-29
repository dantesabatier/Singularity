<?php

namespace App\AI;

use App\Model\Entity;
use App\Model\Relationship;
use Override;
use Sabatier\CoreData\ManagedObject;

final class RelationshipBuilder extends PatchObjectBuilder
{
    #[Override]
    private(set) ManagedObject $object {
        get {
            if (isset($this->object)) {
                return $this->object;
            }
            /** @var AddRelationshipPatchOperation $operation */
            $operation = $this->operation;
            /** @var Entity $entity */
            $entity = $operation->model->entitiesByName[$operation->entityName];
            $relationship = new Relationship($this->managedObjectContext);
            $relationship->name = $operation->name;
            $relationship->lazyDestinationEntityName = $operation->destinationEntityName;
            $relationship->lazyInverseRelationshipName = $operation->inverseRelationshipName;
            $relationship->isToMany = $operation->isToMany;
            $relationship->isOptional = $operation->isOptional;
            $entity->addPropertiesObject($relationship);
            return $this->object = $relationship;
        }
    }

    public function __construct(AddRelationshipPatchOperation $operation)
    {
        parent::__construct($operation);
    }
}
