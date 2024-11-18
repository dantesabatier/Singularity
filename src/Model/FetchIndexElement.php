<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Value;

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
    public ?Property $property {
        get => $this->property ??= $this->property();
    }
    public Dictionary $dictionaryRepresentation {
        get {
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

    #[Override]
    public function awakeFromFetch(): void
    {
        /** @psalm-suppress UndefinedVariable */
        $observation = $this->observe("propertyName", KeyValueObservingOptions::new, function (FetchIndexElement $element, KeyValueObservedChange $change) use (&$observation): void {
            if ($element->isSuppressingKVO || $element->isSuppressingChangeNotifications) {
                return;
            }
            $observation->invalidate();
            if ($change->newValue !== "Expression") {
                $element->expressionFormat = null;
                $element->expressionResultType = AttributeType::undefined;
            }
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

    public function validateExpressionResultType(AttributeType|Number|Nil|int|null &$expressionResultType): bool
    {
        if ($expressionResultType instanceof Value) {
            $expressionResultType = $expressionResultType->value;
        }
        if (is_int($expressionResultType)) {
            $expressionResultType = AttributeType::from($expressionResultType);
        }
        return true;
    }

    private function property(): Attribute
    {
        /** @var ArrayClass<Attribute> $attributes */
        $attributes = new ArrayClass();
        $entity = $this->index->entityProperty;
        if ($entity) {
            $attributes->appendContentsOf($entity->attributes);
            $superentity = $entity->superentity;
            while ($superentity) {
                $attributes->appendContentsOf($superentity->attributes);
                $superentity = $superentity->superentity;
            }
        }
        return $attributes->first(fn(Attribute $attribute): bool => $attribute->name === $this->propertyName);
    }
}
