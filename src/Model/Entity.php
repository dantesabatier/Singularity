<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property string|null $managedObjectClassName
 * @property string|null $renamingIdentifier
 * @property bool $isAbstract
 * @property string|null $versionHashModifier
 * @property-read int $subentitiesCount
 * @property-read int $indexesCount
 * @property Model|null $model
 * @property Entity|null $superentity
 * @property Set<Entity> $subentities
 * @property Set<Property> $properties
 * @property Set<FetchIndex> $indexes
 * @property Set<UniquenessConstraint> $uniquenessConstraints
 * @property bool $isExpanded
 * @property-read ArrayClass<Attribute> $attributes
 * @property-read ArrayClass<Relationship> $relationships
 * @property-read ArrayClass<FetchedProperty> $fetchedProperties
 * @method void addSubentitiesObject(Entity $object)
 * @method void removeSubentitiesObject(Entity $object)
 * @method void addSubentities(Set $objects)
 * @method void removeSubentities(Set $objects)
 * @method Set<Entity> intersectSubentities(Set $objects)
 * @method void setSubentities(Set $objects)
 * @method void addPropertiesObject(Property $object)
 * @method void removePropertiesObject(Property $object)
 * @method void addProperties(Set $objects)
 * @method void removeProperties(Set $objects)
 * @method Set<Property> intersectProperties(Set $objects)
 * @method void setProperties(Set $objects)
 * @method void addIndexesObject(FetchIndex $object)
 * @method void removeIndexesObject(FetchIndex $object)
 * @method void addIndexes(Set $objects)
 * @method void removeIndexes(Set $objects)
 * @method Set<FetchIndex> intersectIndexes(Set $objects)
 * @method void setIndexes(Set $objects)
 * @method void addUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void removeUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void addUniquenessConstraints(Set $objects)
 * @method void removeUniquenessConstraints(Set $objects)
 * @method Set<UniquenessConstraint> intersectUniquenessConstraints(Set $objects)
 * @method void setUniquenessConstraints(Set $objects)
 */
class Entity extends ManagedObject
{
    private(set) ?Entity $rootEntity {
        get => $this->rootEntity ??= $this->rootEntity();
    }
    private(set) bool $isRootEntity {
        get => $this->isRootEntity ??= $this->superentity === null;
    }
    /** @var Dictionary<Attribute> */
    private(set) Dictionary $attributesByName {
        get => $this->attributesByName ??= $this->attributes->reduce(new Dictionary(), function (Dictionary $result, Attribute $attribute): Dictionary {
            $result[$attribute->name] = $attribute;
            return $result;
        });
    }
    /** @var Dictionary<Relationship> */
    private(set) Dictionary $relationshipsByName {
        get => $this->relationshipsByName ??= $this->relationships->reduce(new Dictionary(), function (Dictionary $result, Relationship $relationship): Dictionary {
            $result[$relationship->name] = $relationship;
            return $result;
        });
    }
    /** @var ArrayClass<string> */
    private(set) ArrayClass $allAttributeNames {
        get => $this->allAttributeNames ??= $this->allAttributeNames();
    }
    private(set) bool $isLeaf {
        get => $this->isLeaf ??= !$this->subentitiesCount;
    }
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            $dictionary["managedObjectClassName"] = $this->managedObjectClassName;
            if ($this->isAbstract) {
                $dictionary["isAbstract"] = $this->isAbstract;
            }
            $dictionary["versionHashModifier"] = $this->versionHashModifier;
            $dictionary["renamingIdentifier"] = $this->renamingIdentifier;
            $attributes = $this->attributes->map(fn(Attribute $attribute): Dictionary => $attribute->dictionaryRepresentation);
            if (!$attributes->isEmpty) {
                $dictionary["attributes"] = $attributes;
            }
            $relationships = $this->relationships->map(fn(Relationship $relationship): Dictionary => $relationship->dictionaryRepresentation);
            if (!$relationships->isEmpty) {
                $dictionary["relationships"] = $relationships;
            }
            $fetchedProperties = $this->fetchedProperties->map(fn(FetchedProperty $property): Dictionary => $property->dictionaryRepresentation);
            if (!$fetchedProperties->isEmpty) {
                $dictionary["fetchedProperties"] = $fetchedProperties;
            }
            $uniquenessConstraints = $this->uniquenessConstraints->map(fn(UniquenessConstraint $uniquenessConstraint): ArrayClass => new ArrayClass(explode(",", $uniquenessConstraint->stringValue))->map(fn(string $e): string => trim($e)));
            if (!$uniquenessConstraints->isEmpty) {
                $dictionary["uniquenessConstraints"] = $uniquenessConstraints;
            }
            $indexes = $this->indexes->map(fn(FetchIndex $index): Dictionary => $index->dictionaryRepresentation);
            if (!$indexes->isEmpty) {
                $dictionary["indexes"] = $indexes;
            }
            $subentities = $this->subentities->map(fn(Entity $subentity): Dictionary => $subentity->dictionaryRepresentation);
            if (!$subentities->isEmpty) {
                $dictionary["subentities"] = $subentities;
            }
            return $dictionary;
        }
    }

    private function rootEntity(): ?Entity
    {
        $superentity = $this->superentity;
        $rootEntity = $superentity;
        while ($superentity) {
            $superentity = $superentity->superentity;
            if ($superentity) {
                $rootEntity = $superentity;
            }
        }
        return $rootEntity;
    }

    private function allAttributeNames(): ArrayClass
    {
        /** @var ArrayClass<string> $allAttributeNames */
        $allAttributeNames = new ArrayClass();
        $transform = fn(Attribute $attribute): string => $attribute->name;
        $superentity = $this->superentity;
        while ($superentity) {
            $allAttributeNames->appendContentsOf($superentity->attributes->map($transform));
            $superentity = $superentity->superentity;
        }
        $allAttributeNames->appendContentsOf($this->attributes->map($transform));
        foreach ($this->subentities as $subentity) {
            $allAttributeNames->appendContentsOf($subentity->attributes->map($transform));
        }
        $allAttributeNames[] = "Expression";
        return $allAttributeNames;
    }
}
