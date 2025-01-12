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
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property string|null $partialIndexPredicateFormat
 * @property Entity|null $entityProperty
 * @property FetchIndexElementType $collationType
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
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            $dictionary["partialIndexPredicateFormat"] = $this->partialIndexPredicateFormat;
            $dictionary["elements"] = $this->elements->map(fn(FetchIndexElement $element): Dictionary => $element->dictionaryRepresentation);
            return $dictionary;
        }
    }

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        /** @psalm-suppress UndefinedVariable */
        $observation = $this->observe("collationType", KeyValueObservingOptions::new, function (FetchIndex $index, KeyValueObservedChange $change) use (&$observation): void {
            if ($index->isSuppressingKVO || $index->isSuppressingChangeNotifications) {
                return;
            }
            $observation->invalidate();
            $index->elements->setValueForKey($change->newValue, "collationType");
        });
    }

    public function validateCollationType(FetchIndexElementType|int|null &$collationType): bool
    {
        if (is_int($collationType)) {
            $collationType = FetchIndexElementType::from($collationType);
        }
        return true;
    }
}
