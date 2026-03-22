<?php

namespace App\Model;

use Override;
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
final class FetchIndex extends ManagedObject
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
        $this->observe("collationType", KeyValueObservingOptions::new, function (FetchIndex $index, KeyValueObservedChange $change): void {
            $index->elements->setValueForKey($change->newValue, "collationType");
        });
    }

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }

    public function validateCollationType(FetchIndexElementType|int|null &$collationType): bool
    {
        if (is_int($collationType)) {
            $collationType = FetchIndexElementType::from($collationType);
        }
        return true;
    }
}
