<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\EntityMapping;
use Sabatier\CoreData\EntityMigrationPolicy;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\PropertyMapping;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;

/**
 * How one entity is carried from the source version of a model to the destination one.
 *
 * The two version hashes are what locate this map when a store migrates, and they are taken from the
 * entities of the two versions rather than typed in: a wrong hash makes the engine either reject the
 * map or never find it.
 *
 * @property string|null $name
 * @property string|null $sourceEntityName
 * @property string|null $destinationEntityName
 * @property EntityMapType $type
 * @property string|null $entityMigrationPolicyClassName
 * @property int<0, max> $position
 * @property Dictionary<mixed> $userInfo
 * @property ModelMap|null $modelMap
 * @property Set<PropertyMap> $attributes
 * @property Set<PropertyMap> $relationships
 * @method void addAttributesObject(PropertyMap $object)
 * @method void removeAttributesObject(PropertyMap $object)
 * @method void addAttributes(Set<PropertyMap> $objects)
 * @method void removeAttributes(Set<PropertyMap> $objects)
 * @method Set<PropertyMap> intersectAttributes(Set<PropertyMap> $objects)
 * @method void setAttributes(Set<PropertyMap> $objects)
 * @method void addRelationshipsObject(PropertyMap $object)
 * @method void removeRelationshipsObject(PropertyMap $object)
 * @method void addRelationships(Set<PropertyMap> $objects)
 * @method void removeRelationships(Set<PropertyMap> $objects)
 * @method Set<PropertyMap> intersectRelationships(Set<PropertyMap> $objects)
 * @method void setRelationships(Set<PropertyMap> $objects)
 */
final class EntityMap extends ManagedObject
{
    /** @var string The name the engine gives this mapping, which is what the two entity names spell out when none was typed in. */
    private(set) string $mappingName {
        get {
            if (isset($this->mappingName)) {
                return $this->mappingName;
            }
            if ($name = $this->name) {
                return $this->mappingName = $name;
            }
            $mappingName = $this->sourceEntityName ?? "";
            if ($destinationEntityName = $this->destinationEntityName) {
                $mappingName .= "To$destinationEntityName";
            }
            return $this->mappingName = $mappingName;
        }
    }
    /** @var string|null The version hash of the source entity, read from the frozen version rather than stored: a stale one makes the engine reject the map or never find it. */
    public ?string $sourceEntityVersionHash {
        get => $this->modelMap?->sourceModel?->entitiesByName[$this->sourceEntityName ?? ""]?->versionHash;
    }
    /** @var string|null The version hash of the destination entity, read from the model being edited. */
    public ?string $destinationEntityVersionHash {
        get => $this->modelMap?->model?->managedObjectModel->entitiesByName[$this->destinationEntityName ?? ""]?->versionHash;
    }
    /** @var EntityMapType The type the two sides imply: an entity only the destination has is added, one only the source has is removed, and one on both sides is copied when its hashes agree. */
    public EntityMapType $inferredType {
        get => match (true) {
            $this->sourceEntityName === null => EntityMapType::add,
            $this->destinationEntityName === null => EntityMapType::remove,
            $this->sourceEntityVersionHash !== null && $this->sourceEntityVersionHash === $this->destinationEntityVersionHash => EntityMapType::copy,
            default => EntityMapType::transform,
        };
    }
    /** @var bool Whether the mapping is custom without a policy to carry it out, which the engine refuses to migrate with. */
    public bool $isMissingMigrationPolicy {
        get => $this->type === EntityMapType::custom && $this->migrationPolicyClassName === null;
    }
    /** @var EntityMapping The mapping the engine reads once the map is archived. */
    private(set) EntityMapping $entityMapping {
        get {
            if (isset($this->entityMapping)) {
                return $this->entityMapping;
            }
            $entityMapping = new EntityMapping($this->mappingName);
            $entityMapping->sourceEntityName = $this->sourceEntityName;
            $entityMapping->destinationEntityName = $this->destinationEntityName;
            $entityMapping->sourceEntityVersionHash = $this->sourceEntityVersionHash;
            $entityMapping->destinationEntityVersionHash = $this->destinationEntityVersionHash;
            $entityMapping->mappingType = $this->type->entityMappingType();
            $entityMapping->entityMigrationPolicyClassName = $this->migrationPolicyClassName;
            $entityMapping->attributeMappings = $this->propertyMappings($this->attributes);
            $entityMapping->relationshipMappings = $this->propertyMappings($this->relationships);
            if ($this->userInfo->count > 0) {
                $entityMapping->userInfo = $this->userInfo;
            }
            return $this->entityMapping = $entityMapping;
        }
    }
    /** @var class-string<EntityMigrationPolicy>|null The policy class the engine instantiates, or null when the name does not resolve to one. */
    public ?string $migrationPolicyClassName {
        get {
            $className = $this->entityMigrationPolicyClassName;
            return $className !== null && is_subclass_of($className, EntityMigrationPolicy::class) ? $className : null;
        }
    }

    #[Override]
    public function willSave(): void
    {
        if ($this->name) {
            $this->name = $this->name |> trim(...);
        }
        if ($this->entityMigrationPolicyClassName) {
            $this->entityMigrationPolicyClassName = $this->entityMigrationPolicyClassName |> trim(...);
        }
    }

    public function validateType(EntityMapType|int|null &$type): bool
    {
        if (is_int($type)) {
            $type = EntityMapType::from($type);
        }
        return true;
    }

    /**
     * Returns the mappings a collection of property maps spells out, in the order they are processed.
     * @param Set<PropertyMap> $propertyMaps The property maps to translate.
     * @return ArrayClass<PropertyMapping>
     */
    private function propertyMappings(Set $propertyMaps): ArrayClass
    {
        return new ArrayClass($propertyMaps->map(fn(PropertyMap $propertyMap): PropertyMap => $propertyMap)->sorted([new SortDescriptor("position")]))->map(fn(PropertyMap $propertyMap): PropertyMapping => $propertyMap->propertyMapping);
    }
}
