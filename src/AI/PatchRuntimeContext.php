<?php

namespace App\AI;

use App\Model\Model;
use Sabatier\CoreData\ManagedObjectContext;

final class PatchRuntimeContext
{
    public function __construct(public Model $model, public ManagedObjectContext $managedObjectContext)
    {
    }
}
