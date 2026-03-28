<?php

namespace App\AI;

use App\Model\Attribute;
use App\Model\Entity;
use App\Model\Model;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;

final class AttributeBuilder extends PatchObjectBuilder
{
    #[Override]
    private(set) ManagedObject $object {
        get {
            if (isset($this->object)) {
                return $this->object;
            }
            /** @var AddAttributePatchOperation $operation */
            $operation = $this->operation;
            /** @var Entity $entity */
            $entity = $this->model->entitiesByName[$operation->entityName];
            $attribute = new Attribute($this->managedObjectContext);
            $attribute->name = $operation->name;
            $attribute->type = $operation->attributeType;
            $attribute->isOptional = $operation->isOptional;
            $entity->addPropertiesObject($attribute);
            return $this->object = $attribute;
        }
    }

    public function __construct(AddAttributePatchOperation $operation, ManagedObjectContext $managedObjectContext, Model $model)
    {
        parent::__construct($operation, $managedObjectContext, $model);
    }
}
