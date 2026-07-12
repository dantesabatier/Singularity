<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Predicates\Predicate;

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
        if ($this->predicateString) {
            $this->predicateString = $this->predicateString |> trim(...);
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
