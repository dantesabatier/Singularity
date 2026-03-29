<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property Model|null $model
 * @property Set<Attribute> $elements
 * @method void addElementsObject(Attribute $object)
 * @method void removeElementsObject(Attribute $object)
 * @method void addElements(Set<Attribute> $objects)
 * @method void removeElements(Set<Attribute> $objects)
 * @method Set<Attribute> intersectElements(Set<Attribute> $objects)
 * @method void setElements(Set<Attribute> $objects)
 */
final class CompositeType extends ManagedObject
{
    public AttributeType $type = AttributeType::compositeAttributeType;
    /** @var Dictionary<mixed> */
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

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
