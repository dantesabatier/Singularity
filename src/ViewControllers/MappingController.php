<?php

declare(strict_types=1);

namespace App\ViewControllers;

use App\Bundles\ModelBundle;
use App\Bundles\UpgradeModelTransaction;
use App\Model\EntityMap;
use App\Model\ModelMap;
use App\Model\PropertyMap;
use Exception;
use Override;
use Sabatier\CoreData\EntityMappingType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\MappingModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\ConflictException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use const App\AutomaticallyDeleteMappingModelFilesPreferencesKey;

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
    #[Outlet]
    public bool $automaticallyDeleteMappingModelFiles {
        get => UserDefaults::standard()->bool(AutomaticallyDeleteMappingModelFilesPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, AutomaticallyDeleteMappingModelFilesPreferencesKey);
        }
    }
    /** @var ArrayClass<ManagedObject> */
    #[Outlet]
    private(set) ArrayClass $breadcrumb {
        get => $this->breadcrumb ??= new ArrayClass();
    }
    /** @var ArrayClass<object{name: string, value: int}> The mapping types a menu offers. */
    #[Outlet]
    private(set) ArrayClass $entityMapTypes {
        get => $this->entityMapTypes ??= new ArrayClass(EntityMappingType::cases())->map(fn(EntityMappingType $type): object => (object)["name" => match ($type) {
            EntityMappingType::undefinedEntityMappingType => "Undefined",
            EntityMappingType::customEntityMappingType => "Custom",
            EntityMappingType::addEntityMappingType => "Add",
            EntityMappingType::removeEntityMappingType => "Remove",
            EntityMappingType::copyEntityMappingType => "Copy",
            EntityMappingType::transformEntityMappingType => "Transform",
        }, "value" => $type->value]);
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
    /** @var ArrayClass<string> The versions a map already starts from, so the same pair is not authored twice. */
    #[Outlet]
    private(set) ArrayClass $mappedVersionNames {
        get => $this->mappedVersionNames ??= $this->modelMaps->compactMap(fn(ModelMap $modelMap): ?string => $modelMap->sourceVersionName);
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
        $modelMap->mappingModel = MappingModel::inferredMappingModel($sourceModel, $destinationModel);
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

}
