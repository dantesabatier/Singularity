<?php

namespace App\Model;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
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
 * @method void addSubentitiesObject(Entity $object)
 * @method void removeSubentitiesObject(Entity $object)
 * @method void addSubentities(Set<Entity> $objects)
 * @method void removeSubentities(Set<Entity> $objects)
 * @method Set<Entity> intersectSubentities(Set<Entity> $objects)
 * @method void setSubentities(Set<Entity> $objects)
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
 * @method void addUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void removeUniquenessConstraintsObject(UniquenessConstraint $object)
 * @method void addUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method void removeUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method Set<UniquenessConstraint> intersectUniquenessConstraints(Set<UniquenessConstraint> $objects)
 * @method void setUniquenessConstraints(Set<UniquenessConstraint> $objects)
 */
class Entity extends ManagedObject
{
    public readonly ?Entity $rootEntity;
    public readonly bool $isRootEntity;
    /** @var ArrayClass<Attribute> */
    public readonly ArrayClass $attributes;
    /** @var ArrayClass<Relationship> */
    public readonly ArrayClass $relationships;
    /** @var ArrayClass<FetchedProperty> */
    public readonly ArrayClass $fetchedProperties;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        unset($this->rootEntity);
        unset($this->isRootEntity);
        unset($this->attributes);
        unset($this->relationships);
        unset($this->fetchedProperties);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name == "isRootEntity") {
            $this->$name = $this->superentity === null;
            return $this->$name;
        } elseif ($name == "rootEntity") {
            $superentity = $this->superentity;
            $rootEntity = $superentity;
            while ($superentity) {
                $superentity = $superentity->superentity;
                if ($superentity) {
                    $rootEntity = $superentity;
                }
            }
            $this->$name = $rootEntity;
            return $this->$name;
        } elseif ($name == "attributes") {
            $fetchRequest = Attribute::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["entityProperty", $this]));
            $this->$name = $this->managedObjectContext->fetch($fetchRequest);
            return $this->$name;
        } elseif ($name == "relationships") {
            $fetchRequest = Relationship::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["entityProperty", $this]));
            $this->$name = $this->managedObjectContext->fetch($fetchRequest);
            return $this->$name;
        } elseif ($name == "fetchedProperties") {
            $fetchRequest = FetchedProperty::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["entityProperty", $this]));
            $this->$name = $this->managedObjectContext->fetch($fetchRequest);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == "isRootEntity" || $name == "rootEntity" || $name == "attributes" || $name == "relationships" || $name == "fetchedProperties") {
            $this->$name = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["name"] = $this->name;
        $dictionary["managedObjectClassName"] = $this->managedObjectClassName;
        if ($this->isAbstract) {
            $dictionary["isAbstract"] = $this->isAbstract;
        }
        $dictionary["versionHashModifier"] = $this->versionHashModifier;
        $dictionary["renamingIdentifier"] = $this->renamingIdentifier;
        $attributes = $this->attributes->map(fn(Attribute $attribute): Dictionary => $attribute->dictionaryRepresentation());
        if (!$attributes->isEmpty()) {
            $dictionary["attributes"] = $attributes;
        }
        $relationships = $this->relationships->map(fn(Relationship $relationship): Dictionary => $relationship->dictionaryRepresentation());
        if (!$relationships->isEmpty()) {
            $dictionary["relationships"] = $relationships;
        }
        $fetchedProperties = $this->fetchedProperties->map(fn(FetchedProperty $property): Dictionary => $property->dictionaryRepresentation());
        if (!$fetchedProperties->isEmpty()) {
            $dictionary["fetchedProperties"] = $fetchedProperties;
        }
        $uniquenessConstraints = $this->uniquenessConstraints->map(fn(UniquenessConstraint $uniquenessConstraint): ArrayClass => (new ArrayClass(explode(",", $uniquenessConstraint->stringValue)))->map(fn(string $e): string => trim($e)));
        if (!$uniquenessConstraints->isEmpty()) {
            $dictionary["uniquenessConstraints"] = $uniquenessConstraints;
        }
        $indexes = $this->indexes->map(fn(FetchIndex $index): Dictionary => $index->dictionaryRepresentation());
        if (!$indexes->isEmpty()) {
            $dictionary["indexes"] = $indexes;
        }
        $subentities = $this->subentities->map(fn(Entity $subentity): Dictionary => $subentity->dictionaryRepresentation());
        if (!$subentities->isEmpty()) {
            $dictionary["subentities"] = $subentities;
        }
        return $dictionary;
    }
}
