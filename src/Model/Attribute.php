<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;

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
        $this->observe("type", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            $attribute->attributeValueClassName = match ($attribute->type) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                default => null,
            };
        });
        $this->observe("isMinValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            $attribute->minValue = $attribute->isMinValueBounded ? $this->minValue : null;
        });
        $this->observe("isMaxValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            $attribute->maxValue = $attribute->isMaxValueBounded ? $this->maxValue : null;
        });
        $this->observe("defaultValue", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            if ($attribute->defaultValue === "") {
                $attribute->defaultValue = null;
            }
        });
        $this->observe("isDerived", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            if ($attribute->isDerived) {
                $attribute->defaultValue = null;
            } else {
                $attribute->derivationExpressionFormat = null;
            }
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
            $dictionary["type"] = $type->value;
        }
        if ($this->isDerived) {
            $dictionary["derivationExpressionFormat"] = $this->derivationExpressionFormat;
        } else {
            $dictionary["defaultValue"] = match ($type) {
                AttributeType::string, AttributeType::date => $this->isDefaultValueBounded ? $this->defaultValue : null,
                default => $this->defaultValue
            };
        }
        $dictionary["minValue"] = match ($type) {
            AttributeType::date => $this->isMinValueBounded ? $this->minValue : null,
            default => $this->minValue
        };
        $dictionary["maxValue"] = match ($type) {
            AttributeType::date => $this->isMaxValueBounded ? $this->maxValue : null,
            default => $this->maxValue
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
        if ($isDefaultValueBounded = $this->isDefaultValueBounded) {
            $dictionary["isDefaultValueBounded"] = $isDefaultValueBounded;
        }
        return $dictionary;
    }
}
