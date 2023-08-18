<?php

namespace App\Model;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/**
 * @property string|null $propertyName
 * @property int<0, 2> $collationType
 * @property bool $isAscending
 * @property FetchIndex $index
 */
class FetchIndexElement extends ManagedObject
{
    public ?Property $property = null;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        unset($this->property);
    }

    public function __get(string $name)
    {
        if ($name == "property") {
            $this->$name = $this->index->entityProperty->attributes->first(fn(Attribute $attribute): bool => $attribute->name === $this->propertyName);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

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
