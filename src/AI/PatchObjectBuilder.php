<?php

namespace App\AI;

use App\Model\Model;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;

abstract class PatchObjectBuilder
{
    abstract public ManagedObject $object {
        get;
    }

    public function __construct(public PatchOperation $operation, public ManagedObjectContext $managedObjectContext, public Model $model)
    {
    }
}
