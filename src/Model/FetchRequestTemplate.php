<?php

namespace App\Model;

use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string $name
 * @property string|null $predicateString
 * @property string|null $fetchEntityName
 * @property FetchRequestResultType $fetchResultType
 * @property int $fetchLimit
 * @property int $fetchBatchSize
 * @property bool $includesSubentities
 * @property bool $includesPropertyValues
 * @property bool $returnsObjectsAsFaults
 * @property bool $includesPendingChanges
 * @property bool $returnsDistinctResults
 * @property Model $model
 */
class FetchRequestTemplate extends ManagedObject
{
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            $dictionary["fetchEntityName"] = $this->fetchEntityName;
            $dictionary["predicateString"] = $this->predicateString;
            $fetchResultType = $this->fetchResultType;
            if ($fetchResultType !== FetchRequestResultType::managedObjectResultType) {
                $dictionary["fetchResultType"] = $fetchResultType;
            }
            if ($fetchLimit = $this->fetchLimit) {
                $dictionary["fetchLimit"] = $fetchLimit;
            }
            if ($fetchBatchSize = $this->fetchBatchSize) {
                $dictionary["fetchBatchSize"] = $fetchBatchSize;
            }
            if ($includesSubentities = $this->includesSubentities) {
                $dictionary["includesSubentities"] = $includesSubentities;
            }
            if ($includesPropertyValues = $this->includesPropertyValues) {
                $dictionary["includesPropertyValues"] = $includesPropertyValues;
            }
            if ($returnsObjectsAsFaults = $this->returnsObjectsAsFaults) {
                $dictionary["returnsObjectsAsFaults"] = $returnsObjectsAsFaults;
            }
            if ($includesPendingChanges = $this->includesPendingChanges) {
                $dictionary["includesPendingChanges"] = $includesPendingChanges;
            }
            if ($returnsDistinctResults = $this->returnsDistinctResults) {
                $dictionary["returnsDistinctResults"] = $returnsDistinctResults;
            }
            return $dictionary;
        }
    }

    public function validateFetchResultType(FetchRequestResultType|int|null &$resultType): bool
    {
        if (is_int(value: $resultType)) {
            $resultType = FetchRequestResultType::from($resultType);
        }
        return true;
    }
}
