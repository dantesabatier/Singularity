<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\FetchedPropertyDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\PropertyDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\SortDescriptor;

/**
 * @property string|null $fetchRequestEntityName
 * @property string|null $fetchRequestPredicateFormat
 * @property string|null $fetchRequestSortDescriptorKey
 * @property bool $fetchRequestSortDescriptorIsAscending
 */
final class FetchedProperty extends Property
{
    /** @var list<string> */
    private const array fetchedPropertyDescriptionKeys = ["name", "isOptional", "isTransient", "renamingIdentifier", "versionHashModifier", "isSensitive"];
    /** @var ArrayClass<string> */
    private(set) ArrayClass $fetchedPropertyDescriptionKeys {
        get => $this->fetchedPropertyDescriptionKeys ??= new ArrayClass(self::fetchedPropertyDescriptionKeys);
    }
    private(set) FetchedPropertyDescription $fetchedPropertyDescription {
        get {
            if (isset($this->fetchedPropertyDescription)) {
                return $this->fetchedPropertyDescription;
            }
            $fetchedPropertyDescription = new FetchedPropertyDescription();
            $fetchedPropertyDescription->setValuesForKeys($this->dictionaryWithValues($this->fetchedPropertyDescriptionKeys));
            if (($fetchRequestEntityName = $this->fetchRequestEntityName) && ($fetchRequestPredicateFormat = $this->fetchRequestPredicateFormat)) {
                $fetchRequest = new FetchRequest($fetchRequestEntityName);
                $fetchRequest->predicate = Predicate::format($fetchRequestPredicateFormat);
                if ($fetchRequestSortDescriptorKey = $this->fetchRequestSortDescriptorKey) {
                    $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor($fetchRequestSortDescriptorKey, $this->fetchRequestSortDescriptorIsAscending)]);
                }
                $fetchedPropertyDescription->fetchRequest = $fetchRequest;
            }
            return $this->fetchedPropertyDescription = $fetchedPropertyDescription;
        }
    }
    #[Override]
    public PropertyDescription $propertyDescription {
        get => $this->fetchedPropertyDescription;
    }

    #[Override]
    public function willSave(): void
    {
        if ($this->fetchRequestPredicateFormat) {
            $this->fetchedPropertyDescription = $this->fetchRequestPredicateFormat |> trim(...);
        }
    }
}
