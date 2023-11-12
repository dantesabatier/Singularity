<?php

namespace App\Model;

use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string $name
 * @property string|null $entityName
 * @property string|null $predicateFormat
 * @property FetchRequestResultType $resultType
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
    public function validateFetchResultType(FetchRequestResultType|int|null &$resultType): bool
    {
        if (is_int($resultType)) {
            $resultType = FetchRequestResultType::from($resultType);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["entityName"] = $this->entityName;
        $dictionary["predicateFormat"] = $this->predicateFormat;
        $resultType = $this->resultType;
        if ($resultType !== FetchRequestResultType::managedObjectResultType) {
            $dictionary["resultType"] = $resultType->value;
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
