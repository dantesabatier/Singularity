<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;

/**
 * @property string $propertyName
 * @property FetchIndexElementType $collationType
 * @property bool $isAscending
 * @property AttributeType $expressionResultType
 * @property string|null $expressionFormat
 * @property FetchIndex $index
 */
class FetchIndexElement extends ManagedObject
{
    public readonly ?Property $property;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $observation = $this->observe("propertyName", KeyValueObservingOptions::new, function (FetchIndexElement $element, KeyValueObservedChange $change) use ($managedObjectContext, &$observation): void {
            $observation->invalidate();
            if ($change->newValue !== "Expression") {
                $element->expressionFormat = null;
                $element->expressionResultType = AttributeType::undefined;
            }
        });
        unset($this->property);
    }

    public function __get(string $name)
    {
        if ($name == "property") {
            $this->$name = $this->index->entityProperty?->attributes?->first(fn(Attribute $attribute): bool => $attribute->name === $this->propertyName);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    public function validateCollationType(FetchIndexElementType|Number|Nil|int|null &$collationType): bool
    {
        $collationType = match (true) {
            $collationType instanceof Number => FetchIndexElementType::from($collationType->intValue),
            $collationType instanceof Nil => FetchIndexElementType::bTree,
            is_int($collationType) => FetchIndexElementType::from($collationType),
            default => $collationType
        };
        return true;
    }

    public function validateExpressionResultType(AttributeType|Number|Nil|int|null &$expressionResultType): bool
    {
        $expressionResultType = match (true) {
            $expressionResultType instanceof Number => AttributeType::from($expressionResultType->intValue),
            $expressionResultType instanceof Nil => AttributeType::undefined,
            is_int($expressionResultType) => AttributeType::from($expressionResultType),
            default => $expressionResultType
        };
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["propertyName"] = $this->propertyName;
        $collationType = $this->collationType;
        if ($collationType !== FetchIndexElementType::bTree) {
            $dictionary["collationType"] = $collationType;
        }
        if (!($isAscending = $this->isAscending)) {
            $dictionary["isAscending"] = $isAscending;
        }
        $expressionResultType = $this->expressionResultType;
        if ($expressionResultType !== AttributeType::undefined) {
            $dictionary["expressionResultType"] = $expressionResultType;
        }
        $dictionary["expressionFormat"] = $this->expressionFormat;
        return $dictionary;
    }
}
