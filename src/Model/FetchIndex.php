<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string|null $name
 * @property string|null $partialIndexPredicateFormat
 * @property Entity $entityProperty
 * @property Set<FetchIndexElement> $elements
 * @method void addElementsObject(FetchIndexElement $object)
 * @method void removeElementsObject(FetchIndexElement $object)
 * @method void addElements(Set $objects)
 * @method void removeElements(Set $objects)
 * @method Set<FetchIndexElement> intersectElements(Set $objects)
 * @method void setElements(Set $objects)
 */
class FetchIndex extends ManagedObject
{
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["elements"] = $this->elements->map(fn(FetchIndexElement $element): Dictionary => $element->dictionaryRepresentation());
        return $dictionary;
    }
}
