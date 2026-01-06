<?php

namespace App\ViewControllers;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Service\ViewController;
use const Sabatier\Service\ServiceObjectIDKey;

/**
 * @template ResultType of ManagedObject
 */
abstract class FetchController extends ViewController
{
    /**
     * @param class-string<ResultType> $managedObjectClass
     * @param int $referenceObject
     * @param Dictionary<mixed> $serialization
     * @return ResultType|null
     * @throws Exception
     */
    protected function fetchByReference(string $managedObjectClass, int $referenceObject, Dictionary $serialization)
    {
        $fetchRequest = $managedObjectClass::fetchRequest();
        $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass([ServiceObjectIDKey, $referenceObject]));
        $fetchRequest->serialization = $serialization;
        return $this->managedObjectContext->fetch($fetchRequest)->first ?? null;
    }

    protected function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->httpMethod === HTTPRequestMethod::get ? new URLComponents($this->request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $this->request->parsedBody[$key];
        return is_numeric($referenceObject) ? (int)$referenceObject : null;
    }
}
