<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property Set<Attribute> $elements
 * @property Model|null $model
 * @method void addElementsObject(Attribute $object)
 * @method void removeElementsObject(Attribute $object)
 * @method void addElements(Set $objects)
 * @method void removeElements(Set $objects)
 * @method Set<Attribute> intersectElements(Set $objects)
 * @method void setElements(Set $objects)
 */
class CompositeType extends ManagedObject
{
    public AttributeType $type = AttributeType::compositeAttributeType;
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            $elements = $this->elements;
            if (!$elements->isEmpty) {
                $dictionary["elements"] = $elements->map(fn(Attribute $element) => $element->dictionaryRepresentation);
            }
            return $dictionary;
        }
    }
}
