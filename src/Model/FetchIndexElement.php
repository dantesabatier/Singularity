<?php

namespace App\Model;

use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $propertyName
 * @property int<0, 2> $collationType
 * @property bool $isAscending
 * @property FetchIndex $index
 */
class FetchIndexElement extends ManagedObject
{
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["propertyName"] = $this->propertyName;
        $collationType = FetchIndexElementType::from($this->collationType);
        if ($collationType !== FetchIndexElementType::bTree) {
            $dictionary["collationType"] = $collationType->value;
        }
        return $dictionary;
    }
}
