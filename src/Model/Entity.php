<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property string|null $managedObjectClassName
 * @property string|null $renamingIdentifier
 * @property string|null $versionHashModifier
 * @property bool $isAbstract
 * @property bool $isExpanded
 * @property bool $isAuthorizable
 * @property bool $isLeaf
 * @property bool $isFinal
 * @property Dictionary<float> $position
 * @property-read int $subentitiesCount
 * @property-read int $indexesCount
 * @property Configuration|null $configuration
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
            if (isset($this->attributeNames)) {
                return $this->attributeNames;
            }
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
            return $this->attributeNames = $attributeNames;
        }
    }
    public EntityDescription $entityDescription {
        get {
            if (isset($this->entityDescription)) {
                return $this->entityDescription;
            }
            $entityDescription = new EntityDescription();
            $entityDescription->name = $this->name;
            $entityDescription->managedObjectClassName = $this->managedObjectClassName;
            $entityDescription->renamingIdentifier = $this->renamingIdentifier ?? $this->name;
            $entityDescription->versionHashModifier = $this->versionHashModifier;
            $entityDescription->isAbstract = $this->isAbstract;
            $entityDescription->subentities = new ArrayClass($this->subentities->map(function (Entity $entity) use ($entityDescription) {
                $subentityDescription = $entity->entityDescription;
                $subentityDescription->superentity = $entityDescription;
                return $subentityDescription;
            }));
            $entityDescription->properties = new ArrayClass(new Set($this->attributes->map(fn(Attribute $attribute) => $attribute->attributeDescription))->union($this->relationships->map(fn(Relationship $relationship) => $relationship->relationshipDescription))->union($this->fetchedProperties->map(fn(FetchedProperty $fetchedProperty) => $fetchedProperty->fetchedPropertyDescription)));
            $entityDescription->indexes = new ArrayClass($this->indexes->map(fn(FetchIndex $index) => $index->fetchIndexDescription));
            $entityDescription->uniquenessConstraints = new ArrayClass($this->uniquenessConstraints->map(fn(UniquenessConstraint $uniquenessConstraint): ArrayClass => new ArrayClass(explode(",", $uniquenessConstraint->stringValue))->map(trim(...))));
            return $this->entityDescription = $entityDescription;
        }
    }

    #[Override]
    public function willSave(): void
    {
        if ($this->isDeleted) {
            return;
        }
        $this->name = $this->name |> trim(...);
        if ($this->managedObjectClassName) {
            $this->managedObjectClassName = $this->managedObjectClassName |> trim(...);
        }
        if ($this->renamingIdentifier) {
            $this->renamingIdentifier = $this->renamingIdentifier |> trim(...);
        }
        if ($this->versionHashModifier) {
            $this->versionHashModifier = $this->versionHashModifier |> trim(...);
        }
        $this->isLeaf = (bool)$this->subentitiesCount;
        $this->isFinal = (bool)$this->subentitiesCount;
    }
}
