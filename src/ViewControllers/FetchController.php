<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Service\ViewController;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

abstract class FetchController extends ViewController
{
    /**
     * @template ResultType of ManagedObject
     * @param class-string<ResultType> $managedObjectClass
     * @param int $referenceObject
     * @param Dictionary<mixed>|null $serialization
     * @return ResultType|null
     * @throws Exception
     */
    protected function fetchByReference(string $managedObjectClass, int $referenceObject, ?Dictionary $serialization = null)
    {
        $fetchRequest = $managedObjectClass::fetchRequest();
        $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass([ManagedObjectObjectIDKey, $referenceObject]));
        if ($serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? null;
    }

    protected function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->parameters[$key];
        return is_numeric($referenceObject) ? (int)$referenceObject : null;
    }
}
