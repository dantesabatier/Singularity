<?php

namespace App\Model;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Value;

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
    public readonly ?Entity $fetchEntity;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        unset($this->fetchEntity);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name === "fetchEntity") {
            $fetchRequest = Entity::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["name", $this->fetchEntityName]));
            return $this->managedObjectContext->fetch($fetchRequest)->first;
        }
        return parent::__get($name);
    }

    public function validateFetchResultType(FetchRequestResultType|Number|Nil|int|null &$resultType): bool
    {
        if ($resultType instanceof Value) {
            $resultType = $resultType->value;
        }
        if (is_int(value: $resultType)) {
            $resultType = FetchRequestResultType::from($resultType);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
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
