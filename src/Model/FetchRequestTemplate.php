<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\Predicate;
use Throwable;
use const Sabatier\CoreData\ManagedObjectValidationError;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * @property string $name
 * @property string|null $predicateString
 * @property string|null $fetchEntityName
 * @property FetchRequestResultType $fetchResultType
 * @property int<0, max> $fetchLimit
 * @property int<0, max> $fetchBatchSize
 * @property bool $includesSubentities
 * @property bool $includesPropertyValues
 * @property bool $returnsObjectsAsFaults
 * @property bool $includesPendingChanges
 * @property bool $returnsDistinctResults
 * @property Model $model
 */
final class FetchRequestTemplate extends ManagedObject
{
    private bool $isFetchRequestResolved = false;
    private(set) ?FetchRequest $fetchRequest {
        get {
            if ($this->isFetchRequestResolved) {
                return $this->fetchRequest;
            }
            $this->isFetchRequestResolved = true;
            if (!($fetchEntityName = $this->fetchEntityName)) {
                return $this->fetchRequest = null;
            }
            if (!($entity = $this->model->entitiesByName[$fetchEntityName])) {
                return $this->fetchRequest = null;
            }
            $fetchRequest = new FetchRequest();
            if ($predicateString = $this->predicateString) {
                $fetchRequest->predicate = Predicate::format($predicateString);
            }
            $fetchRequest->entity = $entity->entityDescription;
            $fetchRequest->resultType = $this->fetchResultType;
            $fetchRequest->fetchLimit = $this->fetchLimit;
            $fetchRequest->fetchBatchSize = $this->fetchBatchSize;
            $fetchRequest->includesSubentities = $this->includesSubentities;
            $fetchRequest->includesPropertyValues = $this->includesPropertyValues;
            $fetchRequest->returnsObjectsAsFaults = $this->returnsObjectsAsFaults;
            $fetchRequest->includesPendingChanges = $this->includesPendingChanges;
            $fetchRequest->returnsDistinctResults = $this->returnsDistinctResults;
            return $this->fetchRequest = $fetchRequest;
        }
    }

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->predicateString !== null) {
            $predicateString = $this->predicateString |> trim(...);
            $this->predicateString = $predicateString === "" ? null : $predicateString;
        }
        if ($predicateString = $this->predicateString) {
            try {
                Predicate::format($predicateString);
            } catch (Throwable) {
                throw new InternalInconsistencyException(error: new Error(CocoaErrorDomain, ManagedObjectValidationError, new Dictionary([LocalizedDescriptionKey => "$this->name predicate must be a valid predicate format", LocalizedFailureReasonErrorKey => sprintf("Unable to parse the predicate \"%s\". Check the predicate syntax.", $predicateString)])));
            }
        }
    }

    public function validateFetchResultType(FetchRequestResultType|int|null &$resultType): bool
    {
        if (is_int($resultType)) {
            $resultType = FetchRequestResultType::from($resultType);
        }
        return true;
    }
}
