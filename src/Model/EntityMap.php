<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\EntityDescription;
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
    /** @var Entity|null The entity of the model being edited that this map arrives at, or null when its name names nothing. */
    private(set) ?Entity $destinationEntity {
        get => $this->destinationEntity ??= $this->modelMap?->project?->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $this->destinationEntityName);
    }
    /** @var EntityDescription|null The entity of the frozen version this map starts from, or null when its name names nothing. */
    private(set) ?EntityDescription $sourceEntity {
        get => $this->sourceEntity ??= $this->modelMap?->sourceModel?->entitiesByName[$this->sourceEntityName ?? ""];
    }
    /** @var ArrayClass<string> The source entity's attribute names, offered as a palette because the source property is named inside the expression. */
    private(set) ArrayClass $sourceAttributeNames {
        get => $this->sourceAttributeNames ??= $this->sourceEntity?->attributesByName->keys ?? new ArrayClass();
    }
    /** @var ArrayClass<string> The source entity's relationship names. */
    private(set) ArrayClass $sourceRelationshipNames {
        get => $this->sourceRelationshipNames ??= $this->sourceEntity?->relationshipsByName->keys ?? new ArrayClass();
    }
    /** @var ArrayClass<PropertyMap> The attribute maps in the order the migration processes them. */
    private(set) ArrayClass $orderedAttributes {
        get => $this->orderedAttributes ??= $this->orderedPropertyMaps($this->attributes);
    }
    /** @var ArrayClass<PropertyMap> The relationship maps in the order the migration processes them. */
    private(set) ArrayClass $orderedRelationships {
        get => $this->orderedRelationships ??= $this->orderedPropertyMaps($this->relationships);
    }
    /** @var ArrayClass<string> The destination attributes no property map covers, which keep their default value on migration. */
    private(set) ArrayClass $uncoveredAttributeNames {
        get {
            if (isset($this->uncoveredAttributeNames)) {
                return $this->uncoveredAttributeNames;
            }
            $covered = $this->attributes->map(fn(PropertyMap $propertyMap): string => $propertyMap->name);
            $attributeNames = $this->destinationEntity?->inheritedAttributes->map(fn(Attribute $attribute): string => $attribute->name) ?? new ArrayClass();
            return $this->uncoveredAttributeNames = $attributeNames->filter(fn(string $name): bool => !$covered->containsElement($name));
        }
    }
    /** @var string|null The version hash of the source entity, read from the frozen version rather than stored: a stale one makes the engine reject the map or never find it. */
    public ?string $sourceEntityVersionHash {
        get => $this->sourceEntity?->versionHash;
    }
    /** @var string|null The version hash of the destination entity, read from the model being edited. */
    public ?string $destinationEntityVersionHash {
        get => $this->modelMap?->project?->model?->managedObjectModel->entitiesByName[$this->destinationEntityName ?? ""]?->versionHash;
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
    public EntityMapping $entityMapping {
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
            if (!$this->userInfo->isEmpty) {
                $entityMapping->userInfo = $this->userInfo;
            }
            return $this->entityMapping = $entityMapping;
        }
        set {
            $this->entityMapping = $value;
            $context = $this->managedObjectContext;
            $this->attributes->forEach(fn(PropertyMap $propertyMap) => $context->delete($propertyMap));
            $this->relationships->forEach(fn(PropertyMap $propertyMap) => $context->delete($propertyMap));
            if ($context->hasChanges) {
                $context->save();
            }
            $this->addPropertyMaps($value->attributeMappings, true);
            $this->addPropertyMaps($value->relationshipMappings, false);
            if ($context->hasChanges) {
                $context->save();
            }
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
     * Returns a collection of property maps in the order the migration processes them.
     * @param Set<PropertyMap> $propertyMaps The collection to order.
     * @return ArrayClass<PropertyMap>
     */
    private function orderedPropertyMaps(Set $propertyMaps): ArrayClass
    {
        return new ArrayClass($propertyMaps->map(fn(PropertyMap $propertyMap): PropertyMap => $propertyMap)->sorted([new SortDescriptor("position")]));
    }
    /**
     * Materializes the property maps a collection of mappings spells out.
     * @param ArrayClass<PropertyMapping>|null $propertyMappings The mappings to materialize.
     * @param bool $isAttribute Whether the mappings belong to the attribute collection.
     */
    private function addPropertyMaps(?ArrayClass $propertyMappings, bool $isAttribute): void
    {
        $position = 0;
        foreach ($propertyMappings ?? new ArrayClass() as $propertyMapping) {
            $propertyMap = new PropertyMap($this->managedObjectContext);
            $propertyMap->name = $propertyMapping->name;
            $propertyMap->valueExpressionFormat = $propertyMapping->valueExpression?->description;
            $propertyMap->position = $position++;
            if ($isAttribute) {
                $this->addAttributesObject($propertyMap);
            } else {
                $this->addRelationshipsObject($propertyMap);
            }
        }
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
