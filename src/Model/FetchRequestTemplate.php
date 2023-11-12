<?php

namespace App\Model;

use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string $name
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 * @property FetchRequestResultType $fetchRequestResultType
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
    public function validateFetchRequestResultType(FetchRequestResultType|int|null &$fetchRequestResultType): bool
    {
        if (is_int($fetchRequestResultType)) {
            $fetchRequestResultType = FetchRequestResultType::from($fetchRequestResultType);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
        $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
        $fetchRequestResultType = $this->fetchRequestResultType;
        if ($fetchRequestResultType !== FetchRequestResultType::managedObjectResultType) {
            $dictionary["fetchRequestResultType"] = $fetchRequestResultType->value;
        }
        if ($fetchLimit = $this->fetchLimit) {
            $dictionary["fetchLimit"] = $fetchLimit;
        }
        if ($fetchBatchSize = $this->fetchBatchSize) {
            $dictionary["fetchBatchSize"] = $fetchBatchSize;
        }
        if (!($includesSubentities = $this->includesSubentities)) {
            $dictionary["includesSubentities"] = $includesSubentities;
        }
        if (!($includesPropertyValues = $this->includesPropertyValues)) {
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
