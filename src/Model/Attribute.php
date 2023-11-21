<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use const Sabatier\CoreData\ManagedObjectValidationError;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * @property AttributeType $type
 * @property mixed $defaultValue
 * @property string|null $attributeValueClassName
 * @property string|null $valueTransformerName
 * @property bool $allowsExternalBinaryDataStorage
 * @property bool $preservesValueInHistoryOnDeletion
 * @property string|null $derivationExpressionFormat
 * @property bool $isDerived
 * @property bool $isDefaultValueBounded
 */
class Attribute extends Property
{
    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $observation = $this->observe("type", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            $attribute->attributeValueClassName = match ($change->newValue) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                AttributeType::undefined => throw new InternalInconsistencyException(error: new Error(CocoaErrorDomain, ManagedObjectValidationError, new Dictionary([LocalizedDescriptionKey => "{$attribute->entityProperty->name}.$attribute->name must be a defined type", LocalizedFailureReasonErrorKey => "{$attribute->entityProperty->name}.$attribute->name cannot use an attribute type of \"Undefined\""]))),
                default => null,
            };
            $attribute->isDefaultValueBounded = false;
            $attribute->defaultValue = null;
            $attribute->isMaxValueBounded = false;
            $attribute->minValue = null;
            $attribute->isMinValueBounded = false;
            $attribute->maxValue = null;
        });
        $observation = $this->observe("isMinValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            $attribute->minValue = $change->newValue ? $attribute->minValue : null;
        });
        $observation = $this->observe("isMaxValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            $attribute->maxValue = $change->newValue ? $attribute->maxValue : null;
        });
        $observation = $this->observe("defaultValue", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            if ($change->newValue === "") {
                $attribute->defaultValue = null;
            }
        });
        $observation = $this->observe("isDerived", KeyValueObservingOptions::new, function (/** @noinspection PhpUnusedParameterInspection */ Attribute $attribute, KeyValueObservedChange $change) use (&$observation): void {
            $observation->invalidate();
            $attribute->isTransient = false;
            $attribute->isDefaultValueBounded = false;
            $attribute->defaultValue = null;
            $attribute->isMaxValueBounded = false;
            $attribute->minValue = null;
            $attribute->isMinValueBounded = false;
            $attribute->maxValue = null;
            $attribute->derivationExpressionFormat = null;
        });
    }

    public function validateType(AttributeType|int|null &$type): bool
    {
        if (is_int($type)) {
            $type = AttributeType::from($type);
        }
        return true;
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $type = $this->type;
        if ($type !== AttributeType::undefined) {
            $dictionary["type"] = $type;
        }
        $isDefaultValueBounded = $this->isDefaultValueBounded;
        if ($isDefaultValueBounded) {
            $dictionary["isDefaultValueBounded"] = $isDefaultValueBounded;
        }
        if ($this->isDerived) {
            $dictionary["derivationExpressionFormat"] = $this->derivationExpressionFormat;
        } else {
            $dictionary["defaultValue"] = match ($type) {
                AttributeType::string, AttributeType::date => $isDefaultValueBounded ? $this->defaultValue : null,
                default => $this->defaultValue
            };
            $isMinValueBounded = $this->isMinValueBounded;
            if ($isMinValueBounded) {
                $dictionary["isMinValueBounded"] = $isMinValueBounded;
            }
            $isMaxValueBounded = $this->isMaxValueBounded;
            if ($isMaxValueBounded) {
                $dictionary["isMaxValueBounded"] = $isMaxValueBounded;
            }
            $dictionary["minValue"] = match ($type) {
                AttributeType::date => $isMinValueBounded ? $this->minValue : null,
                default => $this->minValue
            };
            $dictionary["maxValue"] = match ($type) {
                AttributeType::date => $isMaxValueBounded ? $this->maxValue : null,
                default => $this->maxValue
            };
        }
        $attributeValueClassName = $this->attributeValueClassName;
        $dictionary["attributeValueClassName"] = match ($attributeValueClassName) {
            Date::class, UUID::class, URL::class, ManagedObjectID::class => null,
            default => $attributeValueClassName
        };
        if ($valueTransformerName = $this->valueTransformerName) {
            $dictionary["valueTransformerName"] = $valueTransformerName;
        }
        if ($allowsExternalBinaryDataStorage = $this->allowsExternalBinaryDataStorage) {
            $dictionary["allowsExternalBinaryDataStorage"] = $allowsExternalBinaryDataStorage;
        }
        if ($preservesValueInHistoryOnDeletion = $this->preservesValueInHistoryOnDeletion) {
            $dictionary["preservesValueInHistoryOnDeletion"] = $preservesValueInHistoryOnDeletion;
        }
        return $dictionary;
    }
}
