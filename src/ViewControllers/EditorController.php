<?php

namespace App\ViewControllers;

use App\FileWriters\ProjectFileWriter;
use App\FileWriters\SubclassFileWriter;
use App\Model\AccessControl;
use App\Model\CompositeType;
use App\Model\Configuration;
use App\Model\Entity;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Model;
use App\Model\Project;
use App\Model\Property;
use App\Model\Role;
use App\Model\UniquenessConstraint;
use Exception;
use Override;
use ReflectionClass;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Service\Action;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\localized_string;
use const Sabatier\Service\ServiceObjectIDKey;

#[Endpoint("Editor")]
final class EditorController extends ProjectController
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
    public string $name = "Editor";
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        /**
         * @throws Exception
         */
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
    private(set) ?AccessControl $selectedAccessControl = null;
    #[Outlet]
    private(set) ?Role $selectedRole = null;
    #[Outlet]
    private(set) ?ManagedObject $selection = null;
    /** @var ArrayClass<ManagedObject> */
    #[Outlet]
    private(set) ArrayClass $breadcrumb {
        get => $this->breadcrumb ??= new ArrayClass();
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $attributeTypes {
        get => $this->attributeTypes ??= new ArrayClass(AttributeType::cases())->compactMap(fn(AttributeType $type): ?object => match ($type) {
            AttributeType::undefined, AttributeType::decimal, AttributeType::double, AttributeType::float, AttributeType::string, AttributeType::boolean, AttributeType::date, AttributeType::transformable => (object)["name" => ucfirst($type->name), "value" => $type->value],
            AttributeType::uuid, AttributeType::uri => (object)["name" => strtoupper($type->name), "value" => $type->value],
            AttributeType::integer16 => (object)["name" => "Integer 16", "value" => $type->value],
            AttributeType::integer32 => (object)["name" => "Integer 32", "value" => $type->value],
            AttributeType::integer64 => (object)["name" => "Integer 64", "value" => $type->value],
            AttributeType::binaryData => (object)["name" => "Binary Data", "value" => $type->value],
            default => null
        });
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $fetchRequestResultTypes {
        get => $this->fetchRequestResultTypes ??= new ArrayClass(FetchRequestResultType::cases())->compactMap(fn(FetchRequestResultType $type): object => (object)["name" => match ($type) {
            FetchRequestResultType::managedObjectResultType => "Objects",
            FetchRequestResultType::managedObjectIDResultType => "Object IDs",
            FetchRequestResultType::dictionaryResultType => "Dictionaries",
            default => null
        }, "value" => $type->value]);
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $deleteRules {
        get => $this->deleteRules ??= new ArrayClass(DeleteRule::cases())->map(fn(DeleteRule $rule): object => (object)["name" => match ($rule) {
            DeleteRule::noActionDeleteRule => localized_string("No Action"),
            DeleteRule::nullifyDeleteRule => localized_string("Nullify"),
            DeleteRule::cascadeDeleteRule => localized_string("Cascade"),
            DeleteRule::denyDeleteRule => localized_string("Deny"),
        }, "value" => $rule->value]);
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $relationshipTypes {
        get => $this->relationshipTypes ??= new ArrayClass([
            (object)["name" => "To One", "value" => 0],
            (object)["name" => "To Many", "value" => 1],
        ]);
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $collationTypes {
        get => $this->collationTypes ??= new ArrayClass(FetchIndexElementType::cases())->map(fn(FetchIndexElementType $type): object => (object)["name" => match ($type) {
            FetchIndexElementType::binary => localized_string("Binary"),
            FetchIndexElementType::bTree => localized_string("R-Tree"),
            FetchIndexElementType::rTree => localized_string("B-Tree")
        }, "value" => $type->value]);
    }
    /** @var ArrayClass<object{name: string, value: string}> */
    #[Outlet]
    private(set) ArrayClass $booleanValues {
        get => $this->booleanValues ??= new ArrayClass([
            (object)["name" => "None", "value" => ""],
            (object)["name" => "True", "value" => "true"],
            (object)["name" => "False", "value" => "false"],
        ]);
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $scopes {
        get => $this->scopes ??= new ArrayClass(AuthorizationScope::cases())->map(fn(AuthorizationScope $scope): object => (object)["name" => match ($scope) {
            AuthorizationScope::all => localized_string("All"),
            AuthorizationScope::own => localized_string("Own")
        }, "value" => $scope->value]);
    }
    /** @var ArrayClass<string> */
    #[Outlet]
    private(set) ArrayClass $roles {
        get => $this->roles ??= new ArrayClass(["public", "authenticated", "owner", "admin", "moderator", "editor", "viewer"]);
    }
    #[Outlet]
    private(set) bool $isCustomRole {
        get => $this->isCustomRole ??= $this->roles->contains(fn(string $s): bool => $s === $this->selectedRole?->name);
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
        $keys = ["entity", "fetchRequest", "configuration", "composite", "constraint", "property", "index", "element", "accessControl", "role"];
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
                "element" => FetchIndexElement::class,
                "accessControl" => AccessControl::class,
                "role" => Role::class,
            };
            $fetchRequest = $managedObjectClass::fetchRequest();
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ServiceObjectIDKey), Expression::expressionForConstantValue($objectID));
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
            } elseif ($selection instanceof AccessControl) {
                $this->selectedAccessControl = $selection;
            } elseif ($selection instanceof Role) {
                $this->selectedRole = $selection;
            }
            $this->selection = $selection;
            $this->breadcrumb[] = $selection;
        }
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function save(): void
    {
        $project = $this->project;
        $project->lastModifiedDate = new Date();
        $url = $project->url ?? throw new BadRequestException();
        $fileWriter = new ProjectFileWriter($url, $project);
        $fileWriter->save();
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function import(): void
    {
        $body = $this->request->parsedBody;
        /** @var string $path */
        $path = $body["path"] ?? throw new BadRequestException();
        $project = $this->project ?? throw new BadRequestException();
        $project->lastModifiedDate = new Date();
        /** @var Model $model */
        $model = $project->model;
        $model->load(URL::fileURL($path));
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
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
    #[Action(decorators: [JSONDecorator::class])]
    public function reorder(): void
    {
        $body = $this->request->parsedBody;
        /** @var int<0, max> $fromIndex */
        $fromIndex = $body["fromIndex"] ?? throw new BadRequestException();
        /** @var int<0, max> $toIndex */
        $toIndex = $body["toIndex"] ?? throw new BadRequestException();
        /** @var string $key */
        $key = $body["key"] ?? throw new BadRequestException();
        /** @var string $name */
        $name = $body["entity"] ?? throw new BadRequestException();
        /** @var Model $model */
        $model = $this->project->model;
        /** @var Entity $entity */
        $entity = $model->entitiesByName[$name] ?? throw new NotFoundException();
        if ($fromIndex === $toIndex) {
            $this->data = $entity;
            return;
        }
        /** @var ArrayClass<Property> $subset */
        $subset = $entity->valueForKey($key);
        $subset = new ArrayClass($subset->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position", false)]));
        /** @var Property $moved */
        $moved = $subset[$fromIndex];
        /** @var Property $target */
        $target = $subset[$toIndex];
        $properties = new ArrayClass($entity->attributes->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position", false)]));
        $properties->appendContentsOf($entity->relationships->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position", false)]));
        $properties->appendContentsOf($entity->fetchedProperties->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position", false)]));
        $properties->remove($moved);
        $max = $properties->indexBefore($subset->endIndex);
        /** @var int<0, max> $globalToIndex */
        $globalToIndex = $properties->indexOf($target);
        if ($toIndex > $fromIndex) {
            $globalToIndex += 1;
        }
        $globalToIndex = max(min($globalToIndex, $max), 0);
        $properties->insertAt($moved, $globalToIndex);
        $properties = $properties->map(function (Property $property, int $idx): Property {
            $property->position = $idx;
            return $property;
        });
        $entity->properties = new Set($properties);
        $this->managedObjectContext->save();
        $this->data = $entity;
    }
}
