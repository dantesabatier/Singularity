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
 * @property int<0, 2000> $type
 * @property mixed $defaultValue
 * @property string|null $attributeValueClassName
 * @property string|null $valueTransformerName
 * @property bool $allowsExternalBinaryDataStorage
 * @property bool $preservesValueInHistoryOnDeletion
 * @property string|null $derivationExpressionFormat
 * @property bool $isDerived
 */
class Attribute extends Property
{
    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->observe("type", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            $attribute->attributeValueClassName = match (AttributeType::from($attribute->type)) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                default => null,
            };
        });
        $this->observe("defaultValue", KeyValueObservingOptions::new, function (Attribute $attribute): void {
            if ($attribute->defaultValue === "") {
                $attribute->defaultValue = null;
            }
        });
        $this->observe('isDerived', KeyValueObservingOptions::new, function (Attribute $attribute): void {
            if ($attribute->isDerived) {
                $attribute->defaultValue = null;
            } else {
                $attribute->derivationExpressionFormat = null;
            }
        });
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        $type = $this->type;
        if ($type !== AttributeType::undefined->value) {
            $dictionary["type"] = $type;
        }
        if ($this->isDerived) {
            $dictionary["derivationExpressionFormat"] = $this->derivationExpressionFormat;
        } else {
            $dictionary["defaultValue"] = $this->defaultValue;
        }
        return $dictionary;
    }
}
