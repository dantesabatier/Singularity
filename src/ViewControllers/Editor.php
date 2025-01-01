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
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\kCFBundleNameKey;

#[Endpoint]
class Editor extends ViewController
{
    #[Outlet]
    public Project $project {
        get {
            if (!isset($this->project)) {
                if (!($referenceObject = $this->referenceObject("project"))) {
                    throw new NotFoundException();
                }
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
                $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                    "name" => AttributeType::string,
                    "url" => AttributeType::uri,
                    "color" => AttributeType::string,
                    "model" => [
                        "url" => AttributeType::uri
                    ]
                ]);
                $this->project = $this->managedObjectContext->fetch($fetchRequest)->first ?? throw new NotFoundException();
            }
            return $this->project;
        }
    }
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
                /** @phpstan-ignore assign.propertyType */
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
    /** @var array<object{name: string, value: int}> */
    #[Outlet]
    private(set) array $attributeTypes = [];

    private function referenceObject(string $key): ?int
    {
        $request = $this->request;
        $referenceObject = $request->httpMethod === HTTPRequestMethod::get ? new URLComponents($request->url->absoluteString)->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $request->getParsedBody()[$key] ?? null;
        if (is_numeric($referenceObject)) {
            return (int)$referenceObject;
        }
        return null;
    }

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
        $this->attributeTypes = new ArrayClass(AttributeType::cases())->compactMap(fn(AttributeType $type): ?object => match ($type) {
            AttributeType::undefined, AttributeType::decimal, AttributeType::double, AttributeType::float, AttributeType::string, AttributeType::boolean, AttributeType::date, AttributeType::transformable => (object)["name" => ucfirst($type->name), "value" => $type->value],
            AttributeType::uuid, AttributeType::uri => (object)["name" => strtoupper($type->name), "value" => $type->value],
            AttributeType::integer16 => (object)["name" => "Integer 16", "value" => $type->value],
            AttributeType::integer32 => (object)["name" => "Integer 32", "value" => $type->value],
            AttributeType::integer64 => (object)["name" => "Integer 64", "value" => $type->value],
            AttributeType::binaryData => (object)["name" => "Binary Data", "value" => $type->value],
            default => null
        })->array;
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function save(): void
    {
        $project = $this->project ?? throw new BadRequestException("project cannot be null");
        $project->lastModifiedDate = new Date();
        $fileManager = FileManager::default();
        /** @var URL $url */
        $url = $project->url;
        if (!$fileManager->fileExists($url->path)) {
            $fileWriter = new ProjectFileWriter($url);
            $fileWriter->save();
        }
        /** @var Model $model */
        $model = $project->model;
        $this->managedObjectContext->save();
        $bundle = Bundle::bundleWithURL($url);
        /** @var URL $resourceURL */
        $resourceURL = $bundle->resourceURL;
        if (!$fileManager->fileExists($resourceURL->path)) {
            $fileManager->createDirectory($resourceURL, attributes: new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        $modelURL = $resourceURL->appendingPathComponent($bundle->object(kCFBundleNameKey))->appendingPathExtension("plist");
        if ($fileManager->fileExists($modelURL->path)) {
            $fileManager->removeItem($modelURL);
        }
        PropertyListSerialization::writePropertyList($model->dictionaryRepresentation, $modelURL);
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
        $project = $this->project ?? throw new BadRequestException("project cannot be null");
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
            $fileManager->createDirectory($directoryURL, attributes: new Dictionary([FileAttributeKey::posixPermissions => 0777]));
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
}
