<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property Set<Attribute> $elements
 * @property Model|null $model
 * @method void addElementsObject(Attribute $object)
 * @method void removeElementsObject(Attribute $object)
 * @method void addElements(Set $objects)
 * @method void removeElements(Set $objects)
 * @method Set<Attribute> intersectElements(Set $objects)
 * @method void setElements(Set $objects)
 */
class CompositeAttribute extends Attribute
{
    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->type = AttributeType::compositeAttributeType;
    }

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
