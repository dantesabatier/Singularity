<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\CompositeAttributeDescription;
use Sabatier\CoreData\DerivedAttributeDescription;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\PropertyDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Predicates\Expression;
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
 * @property CompositeType|null $compositeType
 */
final class Attribute extends Property
{
    /** @var list<string> */
    private const array attributeDescriptionKeys = ["name", "defaultValue", "isOptional", "isTransient", "renamingIdentifier", "versionHashModifier", "regex", "minValue", "maxValue", "valueTransformerName", "attributeValueClassName", "allowsExternalBinaryDataStorage", "preservesValueInHistoryOnDeletion", "isSensitive"];
    /** @var ArrayClass<string> */
    private(set) ArrayClass $attributeDescriptionKeys {
        get => $this->attributeDescriptionKeys ??= new ArrayClass(self::attributeDescriptionKeys);
    }
    protected ?DerivedAttributeDescription $derivedAttributeDescription {
        get {
            if (!($derivationExpressionFormat = (string)$this->derivationExpressionFormat |> trim(...))) {
                return null;
            }
            $derivedAttributeDescription = new DerivedAttributeDescription();
            $derivedAttributeDescription->name = $this->name;
            $derivedAttributeDescription->type = $this->type;
            $derivedAttributeDescription->derivationExpression = Expression::expressionWithFormat($derivationExpressionFormat);
            return $derivedAttributeDescription;
        }
    }
    protected ?CompositeAttributeDescription $compositeAttributeDescription {
        get {
            if (!($attributeValueClassName = $this->attributeValueClassName)) {
                return null;
            }
            if (!($compositeTypesByName = $this->entityProperty?->model->compositeTypesByName)) {
                return null;
            }
            if (!($compositeType = $compositeTypesByName[$attributeValueClassName])) {
                return null;
            }
            $compositeAttributeDescription = new CompositeAttributeDescription();
            $compositeAttributeDescription->name = $this->name;
            $compositeAttributeDescription->type = AttributeType::compositeAttributeType;
            $compositeAttributeDescription->elements = new ArrayClass($compositeType->elements->map(fn(Attribute $attribute): AttributeDescription => $attribute->attributeDescription));
            return $compositeAttributeDescription;
        }
    }
    private(set) AttributeDescription $attributeDescription {
        get {
            if (isset($this->attributeDescription)) {
                return $this->attributeDescription;
            }
            if ($derivedAttributeDescription = $this->derivedAttributeDescription) {
                return $this->attributeDescription = $derivedAttributeDescription;
            }
            if ($compositeAttributeDescription = $this->compositeAttributeDescription) {
                return $this->attributeDescription = $compositeAttributeDescription;
            }
            $attributeDescription = new AttributeDescription();
            $attributeDescription->name = $this->name;
            $attributeDescription->type = $this->type;
            $attributeDescription->setValuesForKeys($this->dictionaryWithValues($this->attributeDescriptionKeys));
            return $this->attributeDescription = $attributeDescription;
        }
    }
    #[Override]
    public PropertyDescription $propertyDescription {
        get => $this->attributeDescription;
    }

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->observe("type", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            if ($attribute->type === $change->newValue) {
                return;
            }
            $parent = $attribute->entityProperty ?? $attribute->compositeType;
            $attribute->attributeValueClassName = match ($change->newValue) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                AttributeType::transformable => $attribute->attributeValueClassName,
                AttributeType::undefined => throw new InternalInconsistencyException(error: new Error(CocoaErrorDomain, ManagedObjectValidationError, new Dictionary([LocalizedDescriptionKey => "$parent?->name.$attribute->name must be a defined type", LocalizedFailureReasonErrorKey => "$parent?->name.$attribute->name cannot use an attribute type of \"Undefined\""]))),
                default => null,
            };
            $attribute->isDefaultValueBounded = false;
            $attribute->defaultValue = null;
            $attribute->isMaxValueBounded = false;
            $attribute->minValue = null;
            $attribute->isMinValueBounded = false;
            $attribute->maxValue = null;
        });
        $this->observe("isMinValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            $attribute->minValue = match ($attribute->type) {
                AttributeType::date => $change->newValue ? $attribute->minValue : null,
                default => $attribute->minValue
            };
        });
        $this->observe("isMaxValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            $attribute->maxValue = match ($attribute->type) {
                AttributeType::date => $change->newValue ? $attribute->maxValue : null,
                default => $attribute->maxValue
            };
        });
        $this->observe("isDefaultValueBounded", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            if (!$change->newValue) {
                $attribute->defaultValue = null;
            }
        });
        $this->observe("defaultValue", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            if ($change->newValue === "") {
                $attribute->defaultValue = null;
            }
        });
        $this->observe("isDerived", KeyValueObservingOptions::new, function (Attribute $attribute, KeyValueObservedChange $change): void {
            if ($change->newValue) {
                $attribute->isTransient = false;
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
}
