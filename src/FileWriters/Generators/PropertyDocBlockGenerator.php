<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\GeneratedProperty;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchedProperty;
use App\Model\Relationship;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use function Sabatier\Foundation\class_name;

final readonly class PropertyDocBlockGenerator
{
    public function __construct(private PropertyAttributeGenerator $propertyAttributeGenerator)
    {
    }

    /**
     * @param Entity $entity
     * @param Set<string> $existingProperties
     * @param array<string> $reservedPropertyNames
     * @param string $declaration
     * @return Set<string>
     */
    public function generate(Entity $entity, Set $existingProperties, array $reservedPropertyNames, string $declaration): Set
    {
        /** @var Set<string> $properties */
        $properties = new Set($existingProperties);
        $this->generateFromAttributes($entity, $properties, $reservedPropertyNames, $declaration);
        $this->generateFromRelationships($entity, $properties, $reservedPropertyNames, $declaration);
        $this->generateFromFetchedProperties($entity, $properties, $declaration);
        return $properties;
    }

    /**
     * @param Set<string> $properties
     * @param array<string> $reservedPropertyNames
     */
    private function generateFromAttributes(Entity $entity, Set $properties, array $reservedPropertyNames, string $declaration): void
    {
        /** @var Set<string> $attributeProperties */
        $attributeProperties = new Set($entity->attributes->sorted([new SortDescriptor("position", false)]))->compactMap(fn(Attribute $attr) => $this->generateAttributeProperty($attr, $reservedPropertyNames, $declaration));
        $properties->formUnion($attributeProperties);
    }

    /**
     * @param array<string> $reservedPropertyNames
     */
    private function generateAttributeProperty(Attribute $attribute, array $reservedPropertyNames, string $declaration
    ): ?GeneratedProperty
    {
        if (in_array($attribute->name, $reservedPropertyNames)) {
            return null;
        }
        $type = $this->getAttributeType($attribute);
        if (!$type) {
            return null;
        }
        if ($this->propertyAttributeGenerator->shouldGenerateAttributes($attribute)) {
            return null;
        }
        if (str_contains($declaration, "\$$attribute->name")) {
            return null;
        }
        return new GeneratedProperty($attribute->name, $this->formatAttributeType($attribute, $type), $attribute->isDerived, $attribute->isOptional && $type !== "mixed");
    }

    private function getAttributeType(Attribute $attribute): ?string
    {
        $attributeValueClassName = match ($attribute->type) {
            AttributeType::date => Date::class,
            AttributeType::uuid => UUID::class,
            AttributeType::uri => URL::class,
            AttributeType::objectID => ManagedObjectID::class,
            AttributeType::compositeAttributeType => Dictionary::class,
            default => $attribute->attributeValueClassName
        };
        if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
            $className = class_name($attributeValueClassName);
            if ($attribute->type === AttributeType::compositeAttributeType) {
                return "$className<mixed>";
            }
            return $className;
        }
        return match ($attribute->type) {
            AttributeType::transformable => "mixed",
            AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => "int",
            AttributeType::decimal, AttributeType::double => "double",
            AttributeType::float => "float",
            AttributeType::binaryData, AttributeType::string => "string",
            AttributeType::boolean => "bool",
            default => null,
        };
    }

    private function formatAttributeType(Attribute $attribute, string $type): string
    {
        $minValue = $attribute->minValue;
        $maxValue = $attribute->maxValue;
        $isInteger = match ($attribute->type) {
            AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => true,
            default => false
        };
        if ($isInteger && ($minValue !== null || $maxValue !== null)) {
            return sprintf("%s<%s, %s>", $type, $minValue ?? "min", $maxValue ?? "max");
        }
        return $type;
    }

    /**
     * @param Entity $entity
     * @param Set<string> $properties
     * @param array<string> $reservedPropertyNames
     * @param string $declaration
     */
    private function generateFromRelationships(Entity $entity, Set $properties, array $reservedPropertyNames, string $declaration): void
    {
        /** @var ArrayClass<string> $relationships */
        $relationships = $entity->relationships->sorted([new SortDescriptor("position", false)])->compactMap(fn(Relationship $relationship) => $this->generateRelationshipProperty($relationship, $reservedPropertyNames, $declaration, class_name(Set::class)));
        $properties->formUnion($relationships);
    }

    /**
     * @param array<string> $reservedPropertyNames
     */
    private function generateRelationshipProperty(Relationship $relationship, array $reservedPropertyNames, string $declaration, string $setClassName): ?GeneratedProperty
    {
        if (in_array($relationship->name, $reservedPropertyNames)) {
            return null;
        }
        if ($this->propertyAttributeGenerator->shouldGenerateAttributes($relationship)) {
            return null;
        }
        if (str_contains($declaration, "\$$relationship->name")) {
            return null;
        }
        return new GeneratedProperty($relationship->name, $relationship->isToMany ? "$setClassName<$relationship->lazyDestinationEntityName>" : $relationship->lazyDestinationEntityName, false, $relationship->isOptional);
    }

    /**
     * @param Entity $entity
     * @param Set<string> $properties
     * @param string $declaration
     */
    private function generateFromFetchedProperties(Entity $entity, Set $properties, string $declaration): void
    {
        /** @var Set<string> $fetchedProperties */
        $fetchedProperties = $entity->fetchedProperties->sorted([new SortDescriptor("position", false)])->compactMap(fn(FetchedProperty $fp) => $this->generateFetchedProperty($fp, $declaration, class_name(ArrayClass::class)));
        $properties->formUnion($fetchedProperties);
    }

    /**
     * @param FetchedProperty $fetchedProperty
     * @param string $declaration
     * @param string $arrayClassName
     * @return GeneratedProperty|null
     */
    private function generateFetchedProperty(FetchedProperty $fetchedProperty, string $declaration, string $arrayClassName): ?GeneratedProperty
    {
        if ($this->propertyAttributeGenerator->shouldGenerateAttributes($fetchedProperty)) {
            return null;
        }
        if (str_contains($declaration, "\$$fetchedProperty->name")) {
            return null;
        }
        return new GeneratedProperty($fetchedProperty->name, "$arrayClassName<$fetchedProperty->fetchRequestEntityName>", true, false);
    }
}
