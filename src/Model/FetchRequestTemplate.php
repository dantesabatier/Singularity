<?php

namespace App\Model;

use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $name
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 * @property int<0, 3> $fetchRequestResultType
 * @property Model|null $model
 */
class FetchRequestTemplate extends ManagedObject
{
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["fetchRequestEntityName"] = $this->fetchRequestEntityName;
        $dictionary["fetchRequestPredicateFormat"] = $this->fetchRequestPredicateFormat;
        $fetchRequestResultType = FetchRequestResultType::from($this->fetchRequestResultType);
        if ($fetchRequestResultType !== FetchRequestResultType::managedObjectResultType) {
            $dictionary["fetchRequestResultType"] = $fetchRequestResultType->value;
        }
        return $dictionary;
    }
}
