<?php

namespace App\Model;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property Set<Attribute> $elements
 * @method void addElementsObject(Attribute $object)
 * @method void removeElementsObject(Attribute $object)
 * @method void addElements(Set $objects)
 * @method void removeElements(Set $objects)
 * @method Set<Attribute> intersectElements(Set $objects)
 * @method void setElements(Set $objects)
 */
class CompositeAttribute extends Attribute
{
    #[Override]
    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $elements = $this->elements;
        if (!$elements->isEmpty) {
            $dictionary["elements"] = $elements->map(fn(Attribute $element) => $element->dictionaryRepresentation());
        }
        return $dictionary;
    }
}
