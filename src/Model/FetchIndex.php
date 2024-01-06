<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Value;

/**
 * @property string $name
 * @property string|null $partialIndexPredicateFormat
 * @property Entity|null $entityProperty
 * @property FetchIndexElementType $collationType
 * @property Set<FetchIndexElement> $elements
 * @method void addElementsObject(FetchIndexElement $object)
 * @method void removeElementsObject(FetchIndexElement $object)
 * @method void addElements(Set<FetchIndexElement> $objects)
 * @method void removeElements(Set<FetchIndexElement> $objects)
 * @method Set<FetchIndexElement> intersectElements(Set<FetchIndexElement> $objects)
 * @method void setElements(Set<FetchIndexElement> $objects)
 */
class FetchIndex extends ManagedObject
{
    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->observe("collationType", KeyValueObservingOptions::new, function (FetchIndex $index, KeyValueObservedChange $change): void {
            if ($index->isSuppressingKVO || $index->isSuppressingChangeNotifications) {
                return;
            }
            $index->elements->setValueForKey($change->newValue, "collationType");
        });
    }

    public function validateCollationType(FetchIndexElementType|Number|Nil|int|null &$collationType): bool
    {
        if ($collationType instanceof Value) {
            $collationType = $collationType->value;
        }
        if (is_int($collationType)) {
            $collationType = FetchIndexElementType::from($collationType);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["partialIndexPredicateFormat"] = $this->partialIndexPredicateFormat;
        $dictionary["elements"] = $this->elements->map(fn(FetchIndexElement $element): Dictionary => $element->dictionaryRepresentation());
        return $dictionary;
    }
}
