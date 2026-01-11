<?php

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
    public function __construct(
        private AccessControlGenerator $accessControlGenerator
    )
    {
    }

    /**
     * @param Entity $entity
     * @param Set<string> $existingProperties
     * @param string $declaration
     * @return Set<string>
     */
    public function generate(Entity $entity, Set $existingProperties, string $declaration): Set
    {
        /** @var Set<string> $properties */
        $properties = new Set($existingProperties);
        $this->generateFromAttributes($entity, $properties, $declaration);
        $this->generateFromRelationships($entity, $properties, $declaration);
        $this->generateFromFetchedProperties($entity, $properties, $declaration);
        return $properties;
    }

    /**
     * @param Set<string> $properties
     */
    private function generateFromAttributes(Entity $entity, Set $properties, string $declaration): void
    {
        /** @var Set<string> $attributeProperties */
        $attributeProperties = new Set($entity->attributes->sorted([new SortDescriptor("position")]))->compactMap(fn(Attribute $attr) => $this->generateAttributeProperty($attr, $declaration));
        $properties->formUnion($attributeProperties);
    }

    private function generateAttributeProperty(Attribute $attribute, string $declaration
    ): ?string
    {
        $type = $this->getAttributeType($attribute);
        if (!$type) {
            return null;
        }
        if ($this->accessControlGenerator->hasAccessControls($attribute)) {
            return null;
        }
        if (str_contains($declaration, "\$$attribute->name")) {
            return null;
        }
        return new GeneratedProperty($attribute->name, $this->formatAttributeType($attribute, $type), $attribute->isDerived, $attribute->isOptional && $type !== "mixed")->toDocBlock();
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
     * @param string $declaration
     */
    private function generateFromRelationships(Entity $entity, Set $properties, string $declaration): void
    {
        /** @var ArrayClass<string> $relationships */
        $relationships = $entity->relationships->sorted([new SortDescriptor("position")])->compactMap(fn(Relationship $relationship) => $this->generateRelationshipProperty($relationship, $declaration, class_name(Set::class)));
        $properties->formUnion($relationships);
    }

    private function generateRelationshipProperty(Relationship $relationship, string $declaration, string $setClassName): ?string
    {
        if ($this->accessControlGenerator->hasAccessControls($relationship)) {
            return null;
        }
        if (str_contains($declaration, "\$$relationship->name")) {
            return null;
        }
        return new GeneratedProperty($relationship->name, $relationship->isToMany ? "$setClassName<$relationship->lazyDestinationEntityName>" : $relationship->lazyDestinationEntityName, false, $relationship->isOptional)->toDocBlock();
    }

    /**
     * @param Entity $entity
     * @param Set<string> $properties
     * @param string $declaration
     */
    private function generateFromFetchedProperties(Entity $entity, Set $properties, string $declaration): void
    {
        /** @var Set<string> $fetchedProperties */
        $fetchedProperties = $entity->fetchedProperties->sorted([new SortDescriptor("position")])->compactMap(fn(FetchedProperty $fp) => $this->generateFetchedProperty($fp, $declaration, class_name(ArrayClass::class)));
        $properties->formUnion($fetchedProperties);
    }

    /**
     * @param FetchedProperty $fetchedProperty
     * @param string $declaration
     * @param string $arrayClassName
     * @return string|null
     */
    private function generateFetchedProperty(FetchedProperty $fetchedProperty, string $declaration, string $arrayClassName): ?string
    {
        if ($this->accessControlGenerator->hasAccessControls($fetchedProperty)) {
            return null;
        }
        if (str_contains($declaration, "\$$fetchedProperty->name")) {
            return null;
        }
        $type = "$arrayClassName<$fetchedProperty->fetchRequestEntityName>";
        $generatedProperty = new GeneratedProperty($fetchedProperty->name, $type, true, false);
        return $generatedProperty->toDocBlock();
    }
}
