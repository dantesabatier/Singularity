<?php

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\PropertyBlock;
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

/**
 * Generates property blocks (with getters/setters) for properties that have access controls
 */
final readonly class PropertyBlockGenerator
{
    public function __construct(private PropertyAttributeGenerator $propertyAttributeGenerator)
    {
    }

    /**
     * @param Set<string> $uses
     * @param array<string> $reservedPropertyNames
     * @return ArrayClass<PropertyBlock>
     */
    public function generate(Entity $entity, Set $uses, string $declaration, array $reservedPropertyNames = []): ArrayClass
    {
        /** @var ArrayClass<PropertyBlock> $blocks */
        $blocks = new ArrayClass();
        $blocks->appendContentsOf($this->generateFromAttributes($entity, $uses, $declaration, $reservedPropertyNames));
        $blocks->appendContentsOf($this->generateFromRelationships($entity, $uses, $declaration, $reservedPropertyNames));
        $blocks->appendContentsOf($this->generateFromFetchedProperties($entity, $uses, $declaration));
        return $blocks;
    }

    /**
     * @param Set<string> $uses
     * @param array<string> $reservedPropertyNames
     * @return ArrayClass<PropertyBlock>
     */
    private function generateFromAttributes(Entity $entity, Set $uses, string $declaration, array $reservedPropertyNames): ArrayClass
    {
        /** @var ArrayClass<PropertyBlock> */
        return $entity->attributes->sorted([new SortDescriptor("position")])->compactMap(fn(Attribute $attribute): ?PropertyBlock => in_array($attribute->name, $reservedPropertyNames) ? null : $this->generateAttributeBlock($attribute, $uses, $declaration));
    }

    /**
     * @param Set<string> $uses
     */
    private function generateAttributeBlock(Attribute $attribute, Set $uses, string $declaration): ?PropertyBlock
    {
        if (!$this->propertyAttributeGenerator->shouldGenerateAttributes($attribute)) {
            return null;
        }
        if (str_contains($declaration, "\$$attribute->name")) {
            return null;
        }
        if (!($type = $this->getAttributeType($attribute))) {
            return null;
        }
        return new PropertyBlock($attribute->name, $type, $attribute->isOptional && $type !== "mixed", $this->propertyAttributeGenerator->generateAttributes($attribute, $uses));
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
            return class_name($attributeValueClassName);
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

    /**
     * @param Set<string> $uses
     * @param array<string> $reservedPropertyNames
     * @return ArrayClass<PropertyBlock>
     */
    private function generateFromRelationships(Entity $entity, Set $uses, string $declaration, array $reservedPropertyNames): ArrayClass
    {
        $setClassName = class_name(Set::class);
        /** @var ArrayClass<PropertyBlock> */
        return $entity->relationships->sorted([new SortDescriptor("position")])->compactMap(fn(Relationship $relationship): ?PropertyBlock => in_array($relationship->name, $reservedPropertyNames) ? null : $this->generateRelationshipBlock($relationship, $uses, $declaration, $setClassName));
    }

    /**
     * @param Set<string> $uses
     */
    private function generateRelationshipBlock(Relationship $relationship, Set $uses, string $declaration, string $setClassName): ?PropertyBlock
    {
        if (!$this->propertyAttributeGenerator->shouldGenerateAttributes($relationship)) {
            return null;
        }
        if (str_contains($declaration, "\$$relationship->name")) {
            return null;
        }
        return new PropertyBlock($relationship->name, $relationship->isToMany ? $setClassName : $relationship->lazyDestinationEntityName, $relationship->isOptional, $this->propertyAttributeGenerator->generateAttributes($relationship, $uses));
    }

    /**
     * @param Set<string> $uses
     * @return ArrayClass<PropertyBlock>
     */
    private function generateFromFetchedProperties(Entity $entity, Set $uses, string $declaration): ArrayClass
    {
        $arrayClassName = class_name(ArrayClass::class);
        /** @var ArrayClass<PropertyBlock> */
        return $entity->fetchedProperties->sorted([new SortDescriptor("position")])->compactMap(fn(FetchedProperty $property): ?PropertyBlock => $this->generateFetchedPropertyBlock($property, $uses, $declaration, $arrayClassName));
    }

    /**
     * @param Set<string> $uses
     */
    private function generateFetchedPropertyBlock(FetchedProperty $fetchedProperty, Set $uses, string $declaration, string $arrayClassName
    ): ?PropertyBlock
    {
        if (!$this->propertyAttributeGenerator->shouldGenerateAttributes($fetchedProperty)) {
            return null;
        }
        if (str_contains($declaration, "\$$fetchedProperty->name")) {
            return null;
        }
        return new PropertyBlock($fetchedProperty->name, $arrayClassName, false, $this->propertyAttributeGenerator->generateAttributes($fetchedProperty, $uses));
    }
}
