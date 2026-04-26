<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;

abstract class PatchObjectBuilder
{
    abstract public ManagedObject $object {
        get;
    }
    protected ManagedObjectContext $managedObjectContext {
        get => $this->operation->model->managedObjectContext;
    }

    public function __construct(public PatchOperation $operation)
    {
    }
}
