<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\FileWriters\SubclassFileWriter;
use App\Model\CompositeType;
use App\Model\Configuration;
use App\Model\Entity;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Model;
use App\Model\Project;
use App\Model\Property;
use App\Model\UniquenessConstraint;
use Exception;
use Override;
use ReflectionClass;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\fatal_error;

#[Endpoint]
class Editor extends ProjectViewController
{
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        get {
            if (!isset($this->projects)) {
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
                $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                    "name" => AttributeType::string,
                    "color" => AttributeType::string
                ]);
                $projects = $this->managedObjectContext->fetch($fetchRequest);
                if ($projects->count > 1 && ($index = $projects->firstIndex(fn(Project $project): bool => $project->isEqual($this->project)))) {
                    $projects->insertAt($projects->removeAt($index), 0);
                }
                $this->projects = $projects;
            }
            return $this->projects;
        }
    }
    #[Outlet]
    private(set) ?Entity $selectedEntity = null;
    #[Outlet]
    private(set) ?Property $selectedProperty = null;
    #[Outlet]
    private(set) ?FetchIndex $selectedIndex = null;
    #[Outlet]
    private(set) ?FetchIndexElement $selectedIndexElement = null;
    #[Outlet]
    private(set) ?UniquenessConstraint $selectedUniquenessConstraint = null;
    #[Outlet]
    private(set) ?FetchRequestTemplate $selectedFetchRequestTemplate = null;
    #[Outlet]
    private(set) ?Configuration $selectedConfiguration = null;
    #[Outlet]
    private(set) ?CompositeType $selectedCompositeType = null;
    #[Outlet]
    private(set) ?ManagedObject $selection = null;
    /** @var ArrayClass<ManagedObject> */
    #[Outlet]
    private(set) ArrayClass $breadcrumb {
        get => $this->breadcrumb ??= new ArrayClass();
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $attributeTypes;

    private function className(Entity $entity, string $namespace): string
    {
        /** @var class-string $class */
        $class = $entity->managedObjectClassName ?? $entity->name;
        if (!str_contains($class, "\\")) {
            /** @var class-string $class */
            $class = "$namespace\\$class";
        }
        return class_name($class);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function viewWillLoad(): void
    {
        $project = $this->project;
        $this->breadcrumb[] = $project;
        $model = $project->model ?? throw new NotFoundException();
        $this->breadcrumb[] = $model;
        $keys = ["entity", "fetchRequest", "configuration", "composite", "constraint", "property", "index", "element"];
        foreach ($keys as $key) {
            if (!($objectID = $this->referenceObject($key))) {
                continue;
            }
            /** @var class-string<ManagedObject> $managedObjectClass */
            $managedObjectClass = match ($key) {
                "entity" => Entity::class,
                "fetchRequest" => FetchRequestTemplate::class,
                "configuration" => Configuration::class,
                "composite" => CompositeType::class,
                "constraint" => UniquenessConstraint::class,
                "property" => Property::class,
                "index" => FetchIndex::class,
                "element" => FetchIndexElement::class
            };
            $fetchRequest = $managedObjectClass::fetchRequest();
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
            if (!($selection = $this->managedObjectContext->fetch($fetchRequest)->first)) {
                break;
            }
            if ($selection instanceof Entity) {
                $this->selectedEntity = $selection;
            } elseif ($selection instanceof FetchRequestTemplate) {
                $this->selectedFetchRequestTemplate = $selection;
            } elseif ($selection instanceof Configuration) {
                $this->selectedConfiguration = $selection;
            } elseif ($selection instanceof CompositeType) {
                $this->selectedCompositeType = $selection;
            } elseif ($selection instanceof UniquenessConstraint) {
                $this->selectedUniquenessConstraint = $selection;
            } elseif ($selection instanceof Property) {
                $this->selectedProperty = $selection;
            } elseif ($selection instanceof FetchIndex) {
                $this->selectedIndex = $selection;
            } elseif ($selection instanceof FetchIndexElement) {
                $this->selectedIndexElement = $selection;
            }
            $this->selection = $selection;
            $this->breadcrumb[] = $selection;
        }
        /** @var ArrayClass<object{name: string, value: int}> $attributeTypes */
        $attributeTypes = new ArrayClass(AttributeType::cases())->compactMap(fn(AttributeType $type): ?object => match ($type) {
            AttributeType::undefined, AttributeType::decimal, AttributeType::double, AttributeType::float, AttributeType::string, AttributeType::boolean, AttributeType::date, AttributeType::transformable => (object)["name" => ucfirst($type->name), "value" => $type->value],
            AttributeType::uuid, AttributeType::uri => (object)["name" => strtoupper($type->name), "value" => $type->value],
            AttributeType::integer16 => (object)["name" => "Integer 16", "value" => $type->value],
            AttributeType::integer32 => (object)["name" => "Integer 32", "value" => $type->value],
            AttributeType::integer64 => (object)["name" => "Integer 64", "value" => $type->value],
            AttributeType::binaryData => (object)["name" => "Binary Data", "value" => $type->value],
            default => null
        });
        $this->attributeTypes = $attributeTypes;
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function save(): void
    {
        $project = $this->project;
        $project->lastModifiedDate = new Date();
        $url = $project->url ?? throw new BadRequestException("url cannot be null");
        $fileWriter = new ProjectFileWriter($url, $project);
        $fileWriter->save();
        $this->content = json_encode($project, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function import(): void
    {
        $body = $this->request->parsedBody;
        /** @var string $path */
        $path = $body["path"] ?? throw new BadRequestException("path cannot be null");
        $project = $this->project ?? throw new BadRequestException("project cannot be null");
        $project->lastModifiedDate = new Date();
        /** @var Model $model */
        $model = $project->model;
        $model->load(URL::fileURL($path));
        $this->content = json_encode($project, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function subclass(): void
    {
        $project = $this->project;
        /** @var URL $url */
        $url = $project->url;
        /** @var Model $model */
        $model = $project->model;
        $directory = "Model";
        $bundle = Bundle::bundleWithURL($url);
        $principalClass = $bundle->principalClass ?? fatal_error("Unable to load the application principal class");
        $reflectionClass = new ReflectionClass($principalClass);
        $namespace = "{$reflectionClass->getNamespaceName()}\\$directory";
        $fileManager = FileManager::default();
        $sourcesURL = $bundle->bundleURL->appendingPathComponent("src");
        $directoryURL = $sourcesURL->appendingPathComponent($directory);
        if (!$fileManager->fileExists($directoryURL->path)) {
            $fileManager->createDirectory($directoryURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        foreach ($model->entities as $entity) {
            $class = $this->className($entity, $namespace);
            $fileURL = $directoryURL->appendingPathComponent($class)->appendPathExtension("php");
            $fileWriter = new SubclassFileWriter($fileURL, $entity, $class, $namespace, fn(Entity $entity, string $namespace): string => $this->className($entity, $namespace));
            $fileWriter->save();
            $entity->managedObjectClassName = "$namespace\\$class";
        }
        $this->save();
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function reorder(): void
    {
        $body = $this->request->parsedBody;
        /** @var int $fromIndex */
        $fromIndex = $body["fromIndex"] ?? throw new BadRequestException("fromIndex cannot be null");
        /** @var int $toIndex */
        $toIndex = $body["toIndex"] ?? throw new BadRequestException("toIndex cannot be null");
        /** @var string $key */
        $key = $body["key"] ?? throw new BadRequestException("key cannot be null");
        /** @var string $name */
        $name = $body["entity"] ?? throw new BadRequestException("name cannot be null");
        /** @var Model $model */
        $model = $this->project->model;
        /** @var Entity $entity */
        $entity = $model->entitiesByName[$name] ?? throw new BadRequestException("entity cannot be null");
        if ($fromIndex !== $toIndex) {
            /** @var ArrayClass<Property> $value */
            $value = $entity->valueForKey($key) ?? throw new BadRequestException("relationship cannot be null");
            $moved = $value[$fromIndex];
            /** @var Property $target */
            $target = $value[$toIndex];
            $properties = new ArrayClass($entity->properties->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position", false)]));
            $properties->remove($moved);
            $toIndex = $properties->indexOf($target) ?? throw new BadRequestException("toIndex cannot be null");
            $second = $properties->filter(fn(Property $property, int $index): bool => ($index + 1) > $toIndex);
            $second->insertAt($moved, 0);
            $properties->removeAll(fn(Property $property, int $index): bool => ($index + 1) > $toIndex);
            $properties->appendContentsOf($second);
            $properties = $properties->map(function (Property $property, int $index): Property {
                $property->position = $index + 1;
                return $property;
            });
            $entity->properties = new Set($properties);
            $this->managedObjectContext->save();
        }
        $this->content = json_encode($entity, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $this->headerFields["Content-Type"] = "application/json";
    }
}
