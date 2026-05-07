<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchIndexDescription;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;

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
final class FetchIndex extends ManagedObject
{
    private(set) FetchIndexDescription $fetchIndexDescription {
        get {
            if (isset($this->fetchIndexDescription)) {
                return $this->fetchIndexDescription;
            }
            $fetchIndexDescription = new FetchIndexDescription($this->name, new ArrayClass($this->elements->map(fn(FetchIndexElement $element) => $element->fetchIndexElementDescription)));
            if ($partialIndexPredicateFormat = $this->partialIndexPredicateFormat) {
                $fetchIndexDescription->partialIndexPredicate = Predicate::format($partialIndexPredicateFormat);
            }
            return $this->fetchIndexDescription = $fetchIndexDescription;
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
        if ($this->partialIndexPredicateFormat) {
            $this->partialIndexPredicateFormat = $this->partialIndexPredicateFormat |> trim(...);
        }
    }

    public function validateCollationType(FetchIndexElementType|int|null &$collationType): bool
    {
        if (is_int($collationType)) {
            $collationType = FetchIndexElementType::from($collationType);
        }
        return true;
    }
}
