<?php

declare(strict_types=1);

namespace App\ViewControllers;

use App\Bundles\ModelBundle;
use App\Bundles\UpgradeModelTransaction;
use App\Model\EntityMap;
use App\Model\EntityMapType;
use App\Model\ModelMap;
use App\Model\PropertyMap;
use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\MappingModel;
use Sabatier\CoreData\PropertyMapping;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\ConflictException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;

/**
 * The editor for a project's mapping models.
 *
 * The endpoint cannot be named after the entity it edits: a responder is consulted before the
 * persistent space, so calling this "ModelMap" would shadow the CRUD for that entity.
 */
#[Endpoint("Mapping", transformers: [HTMLTransformer::class])]
final class MappingController extends ProjectController
{
    #[Override]
    protected string $name = "Mapping";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
    /** @var ArrayClass<ModelMap> The maps the project holds, oldest first. */
    #[Outlet]
    private(set) ArrayClass $modelMaps {
        get => $this->modelMaps ??= new ArrayClass($this->project->modelMaps->map(fn(ModelMap $modelMap): ModelMap => $modelMap)->sorted([new SortDescriptor("name")]));
    }
    #[Outlet]
    private(set) ?ModelMap $selectedModelMap = null;
    #[Outlet]
    private(set) ?EntityMap $selectedEntityMap = null;
    #[Outlet]
    private(set) ?PropertyMap $selectedPropertyMap = null;
    #[Outlet]
    private(set) ?ManagedObject $selection = null;
    /** @var ArrayClass<ManagedObject> */
    #[Outlet]
    private(set) ArrayClass $breadcrumb {
        get => $this->breadcrumb ??= new ArrayClass();
    }
    /** @var ArrayClass<object{name: string, value: int}> The mapping types a menu offers. */
    #[Outlet]
    private(set) ArrayClass $entityMapTypes {
        get => $this->entityMapTypes ??= new ArrayClass(EntityMapType::cases())->map(fn(EntityMapType $type): object => (object)["name" => ucfirst($type->name), "value" => $type->value]);
    }
    /** @var ArrayClass<string> The versions frozen in the model package, which are what a map can start from. */
    #[Outlet]
    private(set) ArrayClass $sourceVersionNames {
        get {
            if (isset($this->sourceVersionNames)) {
                return $this->sourceVersionNames;
            }
            $modelBundle = $this->modelBundle;
            $currentVersionName = $modelBundle?->currentVersionName;
            return $this->sourceVersionNames = $modelBundle?->versionChecksums->keys->filter(fn(string $name): bool => $name !== $currentVersionName) ?? new ArrayClass();
        }
    }
    /** @var ArrayClass<PropertyMap> The selected entity map's attribute maps, in the order the migration processes them. */
    #[Outlet]
    private(set) ArrayClass $orderedAttributeMaps {
        get => $this->orderedAttributeMaps ??= $this->orderedPropertyMaps($this->selectedEntityMap?->attributes);
    }
    /** @var ArrayClass<PropertyMap> The selected entity map's relationship maps, in the order the migration processes them. */
    #[Outlet]
    private(set) ArrayClass $orderedRelationshipMaps {
        get => $this->orderedRelationshipMaps ??= $this->orderedPropertyMaps($this->selectedEntityMap?->relationships);
    }
    /** @var ArrayClass<string> The versions a map already starts from, so the same pair is not authored twice. */
    #[Outlet]
    private(set) ArrayClass $mappedVersionNames {
        get => $this->mappedVersionNames ??= $this->modelMaps->compactMap(fn(ModelMap $modelMap): ?string => $modelMap->sourceVersionName);
    }
    /** @var ArrayClass<string> The entity names the frozen version holds, which are what a map can come from. */
    #[Outlet]
    private(set) ArrayClass $sourceEntityNames {
        get => $this->sourceEntityNames ??= $this->selectedModelMap?->sourceModel?->entitiesByName->keys->sort() ?? new ArrayClass();
    }
    /** @var ArrayClass<string> The entity names the current version holds, which are what a map can arrive at. */
    #[Outlet]
    private(set) ArrayClass $destinationEntityNames {
        get => $this->destinationEntityNames ??= $this->project->model?->managedObjectModel->entitiesByName->keys->sort() ?? new ArrayClass();
    }
    /** @var ArrayClass<string> The destination entity's attribute names, which is what a property map may name. */
    #[Outlet]
    private(set) ArrayClass $destinationAttributeNames {
        /**
         * @throws Exception
         */
        get => $this->destinationAttributeNames ??= $this->propertyNames($this->selectedEntityMap?->destinationEntityName, false);
    }
    /** @var ArrayClass<string> The destination entity's relationship names. */
    #[Outlet]
    private(set) ArrayClass $destinationRelationshipNames {
        /**
         * @throws Exception
         */
        get => $this->destinationRelationshipNames ??= $this->propertyNames($this->selectedEntityMap?->destinationEntityName, true);
    }
    /** @var ArrayClass<string> The source entity's attribute names, offered as a palette because the source property is named inside the expression. */
    #[Outlet]
    private(set) ArrayClass $sourceAttributeNames {
        /**
         * @throws Exception
         */
        get => $this->sourceAttributeNames ??= $this->sourcePropertyNames($this->selectedEntityMap?->sourceEntityName, false);
    }
    /** @var ArrayClass<string> The source entity's relationship names. */
    #[Outlet]
    private(set) ArrayClass $sourceRelationshipNames {
        /**
         * @throws Exception
         */
        get => $this->sourceRelationshipNames ??= $this->sourcePropertyNames($this->selectedEntityMap?->sourceEntityName, true);
    }
    /** @var ArrayClass<string> The destination attributes no property map covers, which keep their default value on migration. */
    #[Outlet]
    private(set) ArrayClass $uncoveredAttributeNames {
        get {
            if (isset($this->uncoveredAttributeNames)) {
                return $this->uncoveredAttributeNames;
            }
            $entityMap = $this->selectedEntityMap;
            if (!$entityMap) {
                return $this->uncoveredAttributeNames = new ArrayClass();
            }
            $covered = $entityMap->attributes->map(fn(PropertyMap $propertyMap): string => $propertyMap->name);
            return $this->uncoveredAttributeNames = $this->destinationAttributeNames->filter(fn(string $name): bool => !$covered->containsElement($name));
        }
    }
    private ?ModelBundle $modelBundle {
        get {
            if ($this->isModelBundleResolved) {
                return $this->modelBundle;
            }
            $this->isModelBundleResolved = true;
            $url = $this->project->url;
            return $this->modelBundle = $url === null ? null : new ModelBundle($url, $this->project->name);
        }
    }
    private bool $isModelBundleResolved = false;

    /**
     * @throws Exception
     */
    #[Override]
    public function viewWillLoad(): void
    {
        if ($this->request->parameters["partial"] === "1") {
            $this->name = "MappingSelection";
        }
        $project = $this->project;
        $model = $project->model ?? throw new NotFoundException("Model not found");
        $this->breadcrumb->append($project);
        $this->breadcrumb->append($model);
        $keys = ["modelMap", "entityMap", "propertyMap"];
        foreach ($keys as $key) {
            if (!($objectID = $this->referenceObject($key))) {
                continue;
            }
            /** @var class-string<ManagedObject> $managedObjectClass */
            $managedObjectClass = match ($key) {
                "modelMap" => ModelMap::class,
                "entityMap" => EntityMap::class,
                "propertyMap" => PropertyMap::class,
            };
            if (!($selection = $this->fetchByReference($managedObjectClass, $objectID))) {
                break;
            }
            if ($selection instanceof ModelMap) {
                $this->selectedModelMap = $selection;
            } elseif ($selection instanceof EntityMap) {
                $this->selectedEntityMap = $selection;
            } elseif ($selection instanceof PropertyMap) {
                $this->selectedPropertyMap = $selection;
            }
            $this->selection = $selection;
            $this->breadcrumb->append($selection);
        }
    }

    /**
     * Creates a map for a frozen version, seeded with what the inference could work out on its own.
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function seed(): void
    {
        /** @var string $versionName */
        $versionName = $this->request->parameters["version"] ?? throw new BadRequestException("`version` is required");
        $model = $this->project->model ?? throw new NotFoundException("Model not found");
        $modelBundle = $this->modelBundle ?? throw new BadRequestException("Project has no bundle URL");
        $sourceURL = $modelBundle->urlForVersionNamed($versionName);
        FileManager::default()->fileExists($sourceURL->path) ?: throw new NotFoundException("Version `$versionName` was not found in the model package");
        !$this->mappedVersionNames->containsElement($versionName) ?: throw new ConflictException("Version `$versionName` already has a mapping model: a version pair is migrated by exactly one map");
        $sourceModel = new ManagedObjectModel($sourceURL);
        $destinationModel = $model->managedObjectModel;
        $modelMap = new ModelMap($this->managedObjectContext);
        $modelMap->name = "{$versionName}To$modelBundle->currentVersionName";
        $modelMap->sourceVersionName = $versionName;
        $modelMap->sourceModelURL = $sourceURL;
        $this->project->addModelMapsObject($modelMap);
        $this->seedEntityMaps($modelMap, MappingModel::inferredMappingModel($sourceModel, $destinationModel));
        $this->managedObjectContext->save();
        $this->data = $modelMap;
    }

    /**
     * Writes the selected map and hands the store over to the version it arrives at.
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function upgrade(): void
    {
        $objectID = $this->referenceObject("modelMap") ?? throw new BadRequestException("`modelMap` is required");
        $modelMap = $this->fetchByReference(ModelMap::class, $objectID) ?? throw new NotFoundException("Mapping model with objectID `$objectID` was not found");
        $modelMap->sourceModel ?? throw new NotFoundException("The frozen version `$modelMap->sourceVersionName` is not where this map recorded it");
        $modelMap->invalidEntityMaps->isEmpty ?: throw new ConflictException("{$modelMap->invalidEntityMaps->count} entity maps are custom without a migration policy: the engine would refuse to migrate with this map");
        $transaction = new UpgradeModelTransaction($modelMap);
        $transaction->execute();
        $this->data = $modelMap;
    }

    /**
     * Turns an inferred mapping model into the rows the editor shows.
     * @param ModelMap $modelMap The map the rows belong to.
     * @param MappingModel $mappingModel The inference to seed from.
     * @throws Exception
     */
    private function seedEntityMaps(ModelMap $modelMap, MappingModel $mappingModel): void
    {
        $context = $this->managedObjectContext;
        $position = 0;
        foreach ($mappingModel->entityMappings as $entityMapping) {
            $entityMap = new EntityMap($context);
            $entityMap->name = $entityMapping->name;
            $entityMap->sourceEntityName = $entityMapping->sourceEntityName;
            $entityMap->destinationEntityName = $entityMapping->destinationEntityName;
            $entityMap->type = EntityMapType::from($entityMapping->mappingType->value);
            $entityMap->entityMigrationPolicyClassName = $entityMapping->entityMigrationPolicyClassName;
            $entityMap->position = $position++;
            $modelMap->addEntityMapsObject($entityMap);
            $this->seedPropertyMaps($entityMap, $entityMapping->attributeMappings, true);
            $this->seedPropertyMaps($entityMap, $entityMapping->relationshipMappings, false);
        }
    }

    /**
     * @param EntityMap $entityMap The entity map the property maps belong to.
     * @param ArrayClass<PropertyMapping>|null $propertyMappings The inferred mappings.
     * @param bool $isAttribute Whether the mappings belong to the attribute collection.
     * @throws Exception
     */
    private function seedPropertyMaps(EntityMap $entityMap, ?ArrayClass $propertyMappings, bool $isAttribute): void
    {
        $position = 0;
        foreach ($propertyMappings ?? new ArrayClass() as $propertyMapping) {
            $propertyMap = new PropertyMap($this->managedObjectContext);
            $propertyMap->name = $propertyMapping->name;
            $propertyMap->valueExpressionFormat = $propertyMapping->valueExpression?->description;
            $propertyMap->position = $position++;
            if ($isAttribute) {
                $entityMap->addAttributesObject($propertyMap);
            } else {
                $entityMap->addRelationshipsObject($propertyMap);
            }
        }
    }

    /**
     * Returns a collection of property maps in the order the migration processes them.
     * @param Set<PropertyMap>|null $propertyMaps The collection to order, or null when nothing is selected.
     * @return ArrayClass<PropertyMap>
     */
    private function orderedPropertyMaps(?Set $propertyMaps): ArrayClass
    {
        if ($propertyMaps === null) {
            return new ArrayClass();
        }
        return new ArrayClass($propertyMaps->map(fn(PropertyMap $propertyMap): PropertyMap => $propertyMap)->sorted([new SortDescriptor("position")]));
    }

    /**
     * Returns the property names of a destination entity, so a property map offers a menu rather than free text.
     * @param string|null $entityName The destination entity's name.
     * @param bool $isRelationship Whether to return relationship names rather than attribute names.
     * @return ArrayClass<string>
     * @throws Exception
     */
    private function propertyNames(?string $entityName, bool $isRelationship): ArrayClass
    {
        return $this->namesOfEntity($this->project->model?->managedObjectModel, $entityName, $isRelationship);
    }

    /**
     * Returns the property names of a source entity, taken from the frozen version the map starts from.
     * @param string|null $entityName The source entity's name.
     * @param bool $isRelationship Whether to return relationship names rather than attribute names.
     * @return ArrayClass<string>
     * @throws Exception
     */
    private function sourcePropertyNames(?string $entityName, bool $isRelationship): ArrayClass
    {
        return $this->namesOfEntity($this->selectedModelMap?->sourceModel, $entityName, $isRelationship);
    }

    /**
     * @param ManagedObjectModel|null $model The version to read the entity from.
     * @param string|null $entityName The entity's name.
     * @param bool $isRelationship Whether to return relationship names rather than attribute names.
     * @return ArrayClass<string>
     */
    private function namesOfEntity(?ManagedObjectModel $model, ?string $entityName, bool $isRelationship): ArrayClass
    {
        if ($entityName === null) {
            return new ArrayClass();
        }
        $entity = $model?->entitiesByName[$entityName];
        $properties = $isRelationship ? $entity?->relationshipsByName : $entity?->attributesByName;
        return $properties?->keys ?? new ArrayClass();
    }
}
