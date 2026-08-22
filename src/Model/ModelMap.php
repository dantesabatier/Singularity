<?php

declare(strict_types=1);

namespace App\Model;

use App\Bundles\ModelBundle;
use Exception;
use Override;
use Sabatier\CoreData\EntityMapping;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\MappingModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\AutomaticallyDeleteMappingModelFilesPreferencesKey;

/**
 * A map from a frozen version of a model to the one being edited.
 *
 * The store keeps a single editable model, which is always the destination; the source is a version
 * frozen inside the model package, so it is referenced by the URL of its file rather than by a
 * relationship. Its name is kept alongside so the editor can say which version a map starts from
 * without reading the file.
 *
 * @property string $name
 * @property URL|null $sourceModelURL
 * @property string|null $sourceVersionName
 * @property Project|null $project
 * @property Set<EntityMap> $entityMaps
 * @method void addEntityMapsObject(EntityMap $object)
 * @method void removeEntityMapsObject(EntityMap $object)
 * @method void addEntityMaps(Set<EntityMap> $objects)
 * @method void removeEntityMaps(Set<EntityMap> $objects)
 * @method Set<EntityMap> intersectEntityMaps(Set<EntityMap> $objects)
 * @method void setEntityMaps(Set<EntityMap> $objects)
 */
final class ModelMap extends ManagedObject
{
    /** @var ArrayClass<EntityMap> The entity maps in the order the migration processes them. */
    private(set) ArrayClass $orderedEntityMaps {
        get => $this->orderedEntityMaps ??= new ArrayClass($this->entityMaps->map(fn(EntityMap $entityMap): EntityMap => $entityMap)->sorted([new SortDescriptor("position")]));
    }
    /** @var ArrayClass<EntityMap> The entity maps the engine would refuse to migrate with, being custom without a policy. */
    private(set) ArrayClass $invalidEntityMaps {
        get => $this->invalidEntityMaps ??= $this->orderedEntityMaps->filter(fn(EntityMap $entityMap): bool => $entityMap->isMissingMigrationPolicy);
    }
    /** @var ManagedObjectModel|null The frozen version this map starts from, or null when its file is not where the map recorded it. */
    private(set) ?ManagedObjectModel $sourceModel {
        /**
         * @throws Exception
         */
        get {
            if ($this->isSourceModelResolved) {
                return $this->sourceModel;
            }
            $this->isSourceModelResolved = true;
            return $this->sourceModel = ($url = $this->sourceModelURL) === null ? null : new ManagedObjectModel($url);
        }
    }
    /** @var ArrayClass<string> The entity names the frozen version holds, which are what a map can come from. */
    private(set) ArrayClass $sourceEntityNames {
        /**
         * @throws Exception
         */
        get => $this->sourceEntityNames ??= $this->sourceModel?->entitiesByName->keys->sort() ?? new ArrayClass();
    }
    /** @var URL|null The generated mapping model file, or null when the project has no bundle URL. */
    public ?URL $mappingModelURL {
        get {
            $project = $this->project;
            if ($project === null && $this->isDeleted) {
                /** @var Project|null $project */
                $project = $this->committedValues(new ArrayClass(["project"]))->valueForKey("project");
            }
            $bundleURL = $project?->url;
            return $bundleURL === null ? null : new ModelBundle($bundleURL, $bundleURL->lastPathComponent)->urlForMappingModelNamed($this->name);
        }
    }
    /** @var MappingModel The mapping model the engine reads, carrying the two versions the map was authored against. */
    public MappingModel $mappingModel {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->mappingModel)) {
                return $this->mappingModel;
            }
            $mappingModel = new MappingModel();
            $mappingModel->sourceModel = $this->sourceModel;
            $mappingModel->destinationModel = $this->project?->model?->managedObjectModel;
            $mappingModel->entityMappings = $this->orderedEntityMaps->map(fn(EntityMap $entityMap): EntityMapping => $entityMap->entityMapping);
            return $this->mappingModel = $mappingModel;
        }
        /**
         * @throws Exception
         */
        set {
            $this->mappingModel = $value;
            $context = $this->managedObjectContext;
            $this->entityMaps->forEach(fn(EntityMap $entityMap) => $context->delete($entityMap));
            if ($context->hasChanges) {
                $context->save();
            }
            $position = 0;
            foreach ($this->mappingModel->entityMappings as $entityMapping) {
                $entityMap = new EntityMap($context);
                $entityMap->name = $entityMapping->name;
                $entityMap->sourceEntityName = $entityMapping->sourceEntityName;
                $entityMap->destinationEntityName = $entityMapping->destinationEntityName;
                $entityMap->type = $entityMapping->mappingType;
                $entityMap->entityMigrationPolicyClassName = $entityMapping->entityMigrationPolicyClassName;
                $entityMap->userInfo = $entityMapping->userInfo;
                $entityMap->position = $position++;
                $this->addEntityMapsObject($entityMap);
                $entityMap->entityMapping = $entityMapping;
            }
            if ($context->hasChanges) {
                $context->save();
            }
        }
    }
    private bool $isSourceModelResolved = false;

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->sourceVersionName) {
            $this->sourceVersionName = $this->sourceVersionName |> trim(...);
        }
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function prepareForDeletion(): void
    {
        $url = $this->mappingModelURL;
        if (UserDefaults::standard()->bool(AutomaticallyDeleteMappingModelFilesPreferencesKey) && $url !== null && FileManager::default()->fileExists($url->path)) {
            FileManager::default()->removeItem($url);
        }
    }

    public function validateName(?string &$name): bool
    {
        if (is_string($name)) {
            $name = preg_replace("/\s+/", "", $name) ?? $name;
        }
        return true;
    }
}
