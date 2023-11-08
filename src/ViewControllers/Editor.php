<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchedProperty;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Project;
use App\Model\Property;
use App\Model\Relationship;
use App\Model\UniquenessConstraint;
use Exception;
use ReflectionClass;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
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
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\ViewController;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\kCFBundleNameKey;

#[Endpoint]
class Editor extends ViewController
{
    #[Outlet]
    public ?Project $project = null;
    /** @var ArrayClass<Entity> */
    #[Outlet]
    public ArrayClass $allEntities;
    /** @var ArrayClass<Entity> */
    #[Outlet]
    public ArrayClass $rootEntities;
    /** @var ArrayClass<FetchRequestTemplate> */
    #[Outlet]
    public ArrayClass $fetchRequestTemplates;
    #[Outlet]
    public ?Entity $selectedEntity = null;
    #[Outlet]
    public ?Property $selectedProperty = null;
    #[Outlet]
    public ?FetchIndex $selectedIndex = null;
    #[Outlet]
    public ?FetchIndexElement $selectedIndexElement = null;
    #[Outlet]
    public ?UniquenessConstraint $selectedUniquenessConstraint = null;
    #[Outlet]
    public ?FetchRequestTemplate $selectedFetchRequestTemplate = null;

    public function __construct()
    {
        parent::__construct();
        unset($this->project);
        unset($this->rootEntities);
        unset($this->allEntities);
        unset($this->fetchRequestTemplates);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name == "project") {
            $project = null;
            if ($referenceObject = $this->referenceObject("project")) {
                $fetchRequest = Project::fetchRequest();
                $fetchRequest->predicate = Predicate::format("%K == %s", new ArrayClass(["objectID", $referenceObject]));
                $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                    "name" => AttributeType::string,
                    "url" => AttributeType::uri,
                    "model" => [
                        "url" => AttributeType::uri,
                    ]
                ]);
                $project = $this->managedObjectContext->fetch($fetchRequest)->first();
            }
            $this->$name = $project;
            return $this->$name;
        } elseif ($name == "allEntities") {
            $fetchRequest = Entity::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["model", $this->project?->model]));
            $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("name")]);
            $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                "name" => AttributeType::string,
            ]);
            $this->$name = $this->managedObjectContext->fetch($fetchRequest);
            return $this->$name;
        } elseif ($name == "rootEntities") {
            $this->$name = $this->allEntities->filter(fn(Entity $entity): bool => $entity->isRootEntity);
            return $this->$name;
        } elseif ($name == "fetchRequestTemplates") {
            $fetchRequest = FetchRequestTemplate::fetchRequest();
            $fetchRequest->predicate = Predicate::format("%K = %s", new ArrayClass(["model", $this->project?->model]));
            $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("name")]);
            $fetchRequest->serialization = Dictionary::dictionaryWithArray([
                "name" => AttributeType::string,
            ]);
            $this->$name = $this->managedObjectContext->fetch($fetchRequest);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    private function referenceObject(string $key): ?int
    {
        $referenceObject = $this->request->httpMethod === HTTPRequestMethod::get ? (new URLComponents($this->request->url->absoluteString))->queryItems?->first(fn(URLQueryItem $queryItem): bool => $queryItem->name === $key)?->value : $this->request->getParsedBody()[$key] ?? null;
        if (is_numeric($referenceObject)) {
            return (int)$referenceObject;
        }
        return null;
    }

    private function class(Entity $entity, string $namespace): string
    {
        /** @var class-string $class */
        $class = $entity->managedObjectClassName ?? $entity->name;
        if (!str_contains($class, "\\")) {
            /** @var class-string $class */
            $class = "$namespace\\$class";
        }
        return class_name($class);
    }

    private function generateSubclass(Entity $entity, string $class, string $namespace): string
    {
        $setClassName = Set::className();
        $arrayClassName = ArrayClass::className();
        $superentity = $entity->superentity;
        $content = "<?php\n";
        $content .= "\n";
        $content .= "namespace $namespace;\n";
        if (!$superentity) {
            $content .= "\n";
        }
        $attributes = $entity->attributes;
        /** @var ArrayClass<string> $uses */
        $uses = $attributes->compactMap(function (Attribute $attribute): ?string {
            $attributeValueClassName = $attribute->attributeValueClassName ?? match (AttributeType::from($attribute->type)) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                default => null,
            };
            if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
                return "use $attributeValueClassName;";
            }
            return null;
        });
        $relationships = $entity->relationships;
        if ($relationships->contains(fn(Relationship $relationship): bool => $relationship->isToMany)) {
            $uses->append("use " . Set::class . ";");
        }
        $fetchedProperties = $entity->fetchedProperties;
        if (!$fetchedProperties->isEmpty()) {
            $uses->append("use " . ArrayClass::class . ";");
        }
        if (!$superentity) {
            $uses->append("use " . ManagedObject::class . ";");
        }
        if (!$uses->isEmpty()) {
            if ($superentity) {
                $content .= "\n";
            }
            $content .= (new Set($uses))->sort()->join("\n");
        }
        $content .= "\n";
        /** @var ArrayClass<string> $properties */
        $properties = $attributes->compactMap(function (Attribute $attribute): ?string {
            $attributeValueClassName = $attribute->attributeValueClassName ?? match (AttributeType::from($attribute->type)) {
                AttributeType::date => Date::class,
                AttributeType::uuid => UUID::class,
                AttributeType::uri => URL::class,
                AttributeType::objectID => ManagedObjectID::class,
                default => null,
            };
            if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
                $attributeValueClassName = class_name($attributeValueClassName);
            }
            if (!($type = match (AttributeType::from($attribute->type)) {
                AttributeType::undefined, AttributeType::transformable => "mixed",
                AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => "int",
                AttributeType::decimal, AttributeType::double => "double",
                AttributeType::float => "float",
                AttributeType::binaryData, AttributeType::string => "string",
                AttributeType::boolean => "bool",
                default => $attributeValueClassName,
            })) {
                return null;
            }
            $string = " * @property";
            if ($attribute->isDerived) {
                $string .= "-read";
            }
            $string .= " $type";
            $minValue = $attribute->minValue;
            $maxValue = $attribute->maxValue;
            if (($type === "int") && ($minValue !== null || $maxValue !== null)) {
                $string .= sprintf("<%s, %s>", $minValue ?? "min", $maxValue ?? "max");
            }
            if ($attribute->isOptional && $type !== "mixed") {
                $string .= "|null";
            }
            return "$string \$$attribute->name";
        });
        $properties->appendContentsOf($fetchedProperties->map(fn(FetchedProperty $fetchedProperty): string => " * @property-read $arrayClassName<$fetchedProperty->fetchRequestEntityName> \$$fetchedProperty->name"));
        $properties->appendContentsOf($relationships->compactMap(function (Relationship $relationship) use ($setClassName): string {
            $lazyDestinationEntityName = $relationship->lazyDestinationEntityName;
            $string = " * @property ";
            $string .= $relationship->isToMany ? "$setClassName<$lazyDestinationEntityName>" : $lazyDestinationEntityName;
            if ($relationship->isOptional) {
                $string .= "|null";
            }
            return "$string \$$relationship->name";
        }));
        /** @var ArrayClass<string> $methods */
        $methods = $relationships->compactMap(function (Relationship $relationship) use ($namespace, $setClassName): ?string {
            if (!$relationship->isToMany || !($destinationEntity = $relationship->destinationEntity)) {
                return null;
            }
            $relationshipName = ucfirst($relationship->name);
            $entityClassName = $this->class($destinationEntity, $namespace);
            return (new ArrayClass([
                " * @method void add{$relationshipName}Object($entityClassName \$object)",
                " * @method void remove{$relationshipName}Object($entityClassName \$object)",
                " * @method void add$relationshipName($setClassName<$entityClassName> \$objects)",
                " * @method void remove$relationshipName($setClassName<$entityClassName> \$objects)",
                " * @method $setClassName<$entityClassName> intersect$relationshipName($setClassName<$entityClassName> \$objects)",
                " * @method void set$relationshipName($setClassName<$entityClassName> \$objects)"
            ]))->join("\n");
        });
        if (!$uses->isEmpty()) {
            $content .= "\n";
        }
        if (!$properties->isEmpty()) {
            $content .= "/**\n";
            $content .= $properties->join("\n");
            if (!$methods->isEmpty()) {
                $content .= "\n";
                $content .= $methods->join("\n");
            }
            $content .= "\n";
            $content .= " */\n";
        }
        if ($entity->isAbstract) {
            $content .= "abstract ";
        }
        $content .= "class $class extends ";
        $content .= $superentity ? $superentity->name : ManagedObject::className();
        $content .= "\n";
        $content .= "{\n";
        return "$content}\n";
    }

    /**
     * @throws Exception
     */
    public function viewWillLoad(): void
    {
        $keys = ["entity", "fetchRequest", "constraint", "property", "index", "element"];
        foreach ($keys as $key) {
            if (!($objectID = $this->referenceObject($key))) {
                continue;
            }
            /** @var class-string<ManagedObject> $managedObjectClass */
            $managedObjectClass = match ($key) {
                "entity" => Entity::class,
                "fetchRequest" => FetchRequestTemplate::class,
                "constraint" => UniquenessConstraint::class,
                "property" => Property::class,
                "index" => FetchIndex::class,
                "element" => FetchIndexElement::class,
            };
            $fetchRequest = $managedObjectClass::fetchRequest();
            /** @psalm-suppress InternalClass */
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
            if (!($selection = $this->managedObjectContext->fetch($fetchRequest)->first())) {
                break;
            }
            if ($selection instanceof Entity) {
                $this->selectedEntity = $selection;
            } elseif ($selection instanceof FetchRequestTemplate) {
                $this->selectedFetchRequestTemplate = $selection;
            } elseif ($selection instanceof UniquenessConstraint) {
                $this->selectedUniquenessConstraint = $selection;
            } elseif ($selection instanceof Property) {
                $this->selectedProperty = $selection;
            } elseif ($selection instanceof FetchIndex) {
                $this->selectedIndex = $selection;
            } elseif ($selection instanceof FetchIndexElement) {
                $this->selectedIndexElement = $selection;
            }
        }
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function save(): void
    {
        /** @psalm-suppress NullPropertyFetch */
        if (!($project = $this->project) || !($url = $project->url) || !($model = $project->model)) {
            return;
        }
        $fileManager = FileManager::default();
        $bundle = Bundle::bundleWithURL($url);
        $resourceURL = $bundle->bundleURL->appendingPathComponent("Resources");
        if (!$fileManager->fileExists($resourceURL->path)) {
            $fileManager->createDirectory($resourceURL, attributes: new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        $modelURL = $resourceURL->appendingPathComponent($bundle->object(kCFBundleNameKey))->appendingPathExtension("plist");
        if ($fileManager->fileExists($modelURL->path)) {
            $fileManager->removeItem($modelURL);
        }
        PropertyListSerialization::writePropertyList($model->dictionaryRepresentation(), $modelURL);
        $project->lastModifiedDate = new Date();
        $model->url = $modelURL;
        $this->managedObjectContext->save();
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function import(): void
    {
        if (!($url = $this->project?->url) || !($model = $this->project?->model)) {
            return;
        }
        $bundle = Bundle::bundleWithURL($url);
        if (!($modelURL = $bundle->url($bundle->object(kCFBundleNameKey), "plist"))) {
            return;
        }
        $model->load($modelURL);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function subclass(): void
    {
        if (!($url = $this->project?->url) || !($model = $this->project?->model)) {
            return;
        }
        $directory = "Model";
        $bundle = Bundle::bundleWithURL($url);
        $principalClass = $bundle->principalClass ?? fatal_error("Unable to load the application principal class");
        $reflectionClass = new ReflectionClass($principalClass);
        $namespace = "{$reflectionClass->getNamespaceName()}\\$directory";
        $fileManager = FileManager::default();
        $sourcesURL = $bundle->bundleURL->appendingPathComponent("src");
        $attributes = new Dictionary([FileAttributeKey::posixPermissions => 0777]);
        foreach ($model->entities as $entity) {
            $directoryURL = $sourcesURL->appendingPathComponent($directory);
            if (!$fileManager->fileExists($directoryURL->path)) {
                $fileManager->createDirectory($directoryURL, attributes: $attributes);
            }
            $class = $this->class($entity, $namespace);
            $fileURL = $directoryURL->appendingPathComponent($class)->appendPathExtension("php");
            if ($fileManager->createFile($fileURL->path, $this->generateSubclass($entity, $class, $namespace))) {
                $entity->managedObjectClassName = "$namespace\\$class";
            }
        }
        $this->save();
    }
}
