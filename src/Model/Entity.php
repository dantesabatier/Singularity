<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;

/**
 * @property string $name
 * @property string|null $managedObjectClassName
 * @property string|null $renamingIdentifier
 * @property string|null $versionHashModifier
 * @property bool $isAbstract
 * @property bool $isExpanded
 * @property bool $isAuthorizable
 * @property Dictionary<float> $position
 * @property-read int $subentitiesCount
 * @property-read int $indexesCount
 * @property-read bool $isLeaf
 * @property-read bool $isFinal
 * @property Model|null $model
 * @property Entity|null $superentity
 * @property Set<Entity> $subentities
 * @property Set<Property> $properties
 * @property Set<FetchIndex> $indexes
 * @property Set<UniquenessConstraint> $uniquenessConstraints
 * @property-read ArrayClass<Attribute> $attributes
 * @property-read ArrayClass<Relationship> $relationships
 * @property-read ArrayClass<FetchedProperty> $fetchedProperties
 * @method void addPropertiesObject(Property $object)
 * @method void removePropertiesObject(Property $object)
 * @method void addProperties(Set<Property> $objects)
 * @method void removeProperties(Set<Property> $objects)
 * @method Set<Property> intersectProperties(Set<Property> $objects)
 * @method void setProperties(Set<Property> $objects)
 * @method void addIndexesObject(FetchIndex $object)
 * @method void removeIndexesObject(FetchIndex $object)
 * @method void addIndexes(Set<FetchIndex> $objects)
 * @method void removeIndexes(Set<FetchIndex> $objects)
 * @method Set<FetchIndex> intersectIndexes(Set<FetchIndex> $objects)
 * @method void setIndexes(Set<FetchIndex> $objects)
 * @method void addSubentitiesObject(Entity $object)
 * @method void removeSubentitiesObject(Entity $object)
 * @method void addSubentities(Set<Entity> $objects)
 * @method void removeSubentities(Set<Entity> $objects)
 * @method Set<Entity> intersectSubentities(Set<Entity> $objects)
 * @method void setSubentities(Set<Entity> $objects)
 * @method void addUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void removeUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void addUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method void removeUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method Set<UniquenessConstraint> intersectUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method void setUniquenessConstraints(Set<UniquenessConstraint> $objects)
 */
final class Entity extends ManagedObject
{
    private(set) ?Entity $rootEntity {
        get {
            if (!isset($this->rootEntity)) {
                $superentity = $this->superentity;
                $rootEntity = $superentity;
                while ($superentity) {
                    $superentity = $superentity->superentity;
                    if ($superentity) {
                        $rootEntity = $superentity;
                    }
                }
                $this->rootEntity = $rootEntity;
            }
            return $this->rootEntity;
        }
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
    private(set) ArrayClass $attributeNames {
        get {
            if (!isset($this->attributeNames)) {
                /** @var ArrayClass<string> $attributeNames */
                $attributeNames = new ArrayClass();
                $transform = fn(Attribute $attribute): string => $attribute->name;
                $superentity = $this->superentity;
                while ($superentity) {
                    $attributeNames->appendContentsOf($superentity->attributes->map($transform));
                    $superentity = $superentity->superentity;
                }
                $attributeNames->appendContentsOf($this->attributes->map($transform));
                foreach ($this->subentities as $subentity) {
                    $attributeNames->appendContentsOf($subentity->attributes->map($transform));
                }
                $attributeNames->append("Expression");
                $this->attributeNames = $attributeNames;
            }
            return $this->attributeNames;
        }
    }
    /** @var Dictionary<mixed> */
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
            $attributes = $this->attributes->sorted([new SortDescriptor("position", false)])->map(fn(Attribute $attribute): Dictionary => $attribute->dictionaryRepresentation);
            if (!$attributes->isEmpty) {
                $dictionary["attributes"] = $attributes;
            }
            $relationships = $this->relationships->sorted([new SortDescriptor("position", false)])->map(fn(Relationship $relationship): Dictionary => $relationship->dictionaryRepresentation);
            if (!$relationships->isEmpty) {
                $dictionary["relationships"] = $relationships;
            }
            $fetchedProperties = $this->fetchedProperties->sorted([new SortDescriptor("position", false)])->map(fn(FetchedProperty $property): Dictionary => $property->dictionaryRepresentation);
            if (!$fetchedProperties->isEmpty) {
                $dictionary["fetchedProperties"] = $fetchedProperties;
            }
            $uniquenessConstraints = $this->uniquenessConstraints->map(fn(UniquenessConstraint $uniquenessConstraint): ArrayClass => new ArrayClass(explode(",", $uniquenessConstraint->stringValue))->map(trim(...)));
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

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
