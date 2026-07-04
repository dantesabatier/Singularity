<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchIndexElementDescription;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Predicates\Expression;

/**
 * @property string $propertyName
 * @property FetchIndexElementType $collationType
 * @property bool $isAscending
 * @property AttributeType $expressionResultType
 * @property string|null $expressionFormat
 * @property FetchIndex $index
 */
final class FetchIndexElement extends ManagedObject
{
    private(set) ?Property $property {
        get {
            if (isset($this->property)) {
                return $this->property;
            }
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
            return $this->property = $attributes->first(fn(Attribute $attribute): bool => $attribute->name === $this->propertyName);
        }
    }
    private(set) FetchIndexElementDescription $fetchIndexElementDescription {
        get {
            if (isset($this->fetchIndexElementDescription)) {
                return $this->fetchIndexElementDescription;
            }
            if ($expressionFormat = $this->expressionFormat) {
                $propertyDescription = new ExpressionDescription();
                $propertyDescription->name = $this->index->name;
                $propertyDescription->expression = Expression::expressionWithFormat($expressionFormat);
                $propertyDescription->resultType = $this->expressionResultType;
            } else {
                $propertyDescription = $this->property->propertyDescription;
            }
            $fetchIndexElementDescription = new FetchIndexElementDescription($propertyDescription, $this->collationType);
            $fetchIndexElementDescription->isAscending = $this->isAscending;
            return $this->fetchIndexElementDescription = $fetchIndexElementDescription;
        }
    }

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->observe("propertyName", KeyValueObservingOptions::new, function (FetchIndexElement $element, KeyValueObservedChange $change): void {
            if ($change->newValue !== "Expression") {
                $element->expressionFormat = null;
                $element->expressionResultType = AttributeType::undefined;
            }
        });
    }

    #[Override]
    public function willSave(): void
    {
        $this->propertyName = $this->propertyName |> trim(...);
        if ($this->expressionFormat) {
            $this->expressionFormat = $this->expressionFormat |> trim(...);
        }
    }

    public function validateCollationType(FetchIndexElementType|int|null &$collationType): bool
    {
        if (is_int($collationType)) {
            $collationType = FetchIndexElementType::from($collationType);
        }
        return true;
    }

    public function validateExpressionResultType(AttributeType|int|null &$expressionResultType): bool
    {
        if (is_int($expressionResultType)) {
            $expressionResultType = AttributeType::from($expressionResultType);
        }
        return true;
    }
}
