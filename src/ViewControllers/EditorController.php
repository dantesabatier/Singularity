<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace App\ViewControllers;

use App\Bundles\BundleUpdater;
use App\Bundles\ExtractLocalizablesTransaction;
use App\Bundles\NewModelVersionTransaction;
use App\Bundles\SaveBundleTransaction;
use App\Bundles\SubclassTransaction;
use App\FileWriters\Generators\AuthorizableCodeGenerator;
use App\FileWriters\Generators\AuthorizableRoleCodeGenerator;
use App\FileWriters\Generators\AuthorizationCodeGenerator;
use App\FileWriters\Generators\PropertyAttributeGenerator;
use App\LLM\Provider;
use App\Model\AccessControl;
use App\Model\Attachment;
use App\Model\Attribute;
use App\Model\CompositeType;
use App\Model\Configuration;
use App\Model\Conversation;
use App\Model\Entity;
use App\Model\EntityType;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Message;
use App\Model\Project;
use App\Model\Property;
use App\Model\Relationship;
use App\Model\Role;
use App\Model\ToolCall;
use App\Model\ToolCallStatus;
use App\Model\UniquenessConstraint;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\FetchIndexElementType;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\KeyedUnarchiver;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\HTMLTransformer;
use Sabatier\Service\InternalServerErrorException;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\MCP\MCPInstructionsProvider;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchemaExtractor;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\SchemaLocalizer;
use Sabatier\Service\MCP\Schema\VocabularyRepository;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Outlet;
use Throwable;
use const App\EditorCopilotEnabledPreferencesKey;
use const App\EditorGraphViewValue;
use const App\EditorSelectedViewPreferencesKey;
use const App\EditorSplitSizesPreferencesKey;
use const App\EditorTableViewValue;
use const App\LLMModelPreferencesKey;
use const App\LLMProviderPreferencesKey;
use const Sabatier\CoreData\SQLStoreType;
use const Sabatier\Foundation\kCFBundleDocumentTypesKey;
use const Sabatier\Foundation\kCFBundleTypeNameKey;

#[Endpoint("Editor", transformers: [HTMLTransformer::class])]
final class EditorController extends ProjectController
{
    public const string tableViewValue = EditorTableViewValue;
    public const string graphViewValue = EditorGraphViewValue;
    #[Override]
    protected string $name = "Editor";
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
    #[Outlet]
    public ?string $selectedView {
        get => UserDefaults::standard()->string(EditorSelectedViewPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorSelectedViewPreferencesKey);
        }
    }
    #[Outlet]
    public ?ArrayClass $splitSizes {
        get => UserDefaults::standard()->array(EditorSplitSizesPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, EditorSplitSizesPreferencesKey);
        }
    }
    #[Outlet]
    public bool $isCopilotEnabled {
        get => UserDefaults::standard()->bool(EditorCopilotEnabledPreferencesKey);
        set {
            UserDefaults::standard()->setBool($value, EditorCopilotEnabledPreferencesKey);
        }
    }
    #[Outlet]
    public string $selectedLLMProviderIdentifier {
        get => UserDefaults::standard()->string(LLMProviderPreferencesKey) ?? "anthropic";
        set {
            UserDefaults::standard()->setObject($value, LLMProviderPreferencesKey);
        }
    }
    #[Outlet]
    public ?string $selectedAIModel {
        get => UserDefaults::standard()->string(LLMModelPreferencesKey);
        set {
            UserDefaults::standard()->setObject($value, LLMModelPreferencesKey);
        }
    }
    /** @var ArrayClass<Provider> */
    #[Outlet]
    private(set) ArrayClass $aiProviders {
        get => $this->aiProviders ??= Provider::all();
    }
    #[Outlet]
    public bool $isTableViewSelected {
        get => $this->selectedView === self::tableViewValue;
    }
    #[Outlet]
    public bool $isGraphViewSelected {
        get => $this->selectedView === self::graphViewValue;
    }
    #[Outlet]
    private(set) bool $isSQLViewerEnabled {
        get {
            if (isset($this->isSQLViewerEnabled)) {
                return $this->isSQLViewerEnabled;
            }
            if (!($url = $this->project->url)) {
                return $this->isSQLViewerEnabled = false;
            }
            /** @var ArrayClass<Dictionary<string>> $documentTypes */
            $documentTypes = Bundle::bundleWithURL($url)->object(kCFBundleDocumentTypesKey);
            return $this->isSQLViewerEnabled = $documentTypes->contains(fn(Dictionary $dictionary): bool => $dictionary[kCFBundleTypeNameKey] === SQLStoreType);
        }
    }
    /** @var ArrayClass<Project> */
    #[Outlet]
    private(set) ArrayClass $projects {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->projects)) {
                return $this->projects;
            }
            $fetchRequest = Project::fetchRequest();
            $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
            $projects = $this->managedObjectContext->fetch($fetchRequest);
            if ($projects->count > 1 && ($index = $projects->firstIndex(fn(Project $project): bool => $project->isEqual($this->project)))) {
                $projects->insertAt($projects->removeAt($index), 0);
            }
            return $this->projects = $projects;
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
    private(set) ?Conversation $selectedConversation {
        get {
            if (isset($this->selectedConversation)) {
                return $this->selectedConversation;
            }
            if ($this->request->parameters["fresh"] === "1") {
                return null;
            }
            return $this->selectedConversation = $this->project->selectedConversation;
        }
    }
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
            DeleteRule::noActionDeleteRule => "No Action",
            DeleteRule::nullifyDeleteRule => "Nullify",
            DeleteRule::cascadeDeleteRule => "Cascade",
            DeleteRule::denyDeleteRule => "Deny",
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
            FetchIndexElementType::binary => "Binary",
            FetchIndexElementType::bTree => "R-Tree",
            FetchIndexElementType::rTree => "B-Tree"
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
            AuthorizationScope::all => "All",
            AuthorizationScope::own => "Own"
        }, "value" => $scope->value]);
    }
    /** @var ArrayClass<object{name: string, value: int}> */
    #[Outlet]
    private(set) ArrayClass $entityTypes {
        get => $this->entityTypes ??= new ArrayClass(EntityType::cases())->map(fn(EntityType $type): object => (object)["name" => match ($type) {
            EntityType::none => "None",
            EntityType::authorizable => "Authorizable",
            EntityType::authorizableRole => "Authorizable Role",
            EntityType::authorization => "Authorization"
        }, "value" => $type->value]);
    }
    /** @var Set<string> */
    #[Outlet]
    private(set) Set $missingReservedProperties {
        get {
            if (isset($this->missingReservedProperties)) {
                return $this->missingReservedProperties;
            }
            $entity = $this->selectedEntity;
            if ($entity === null || $entity->type === EntityType::none) {
                return $this->missingReservedProperties = new Set();
            }
            $accessControlGenerator = new PropertyAttributeGenerator();
            $reservedPropertyNames = match ($entity->type) {
                EntityType::authorizable => new AuthorizableCodeGenerator($accessControlGenerator)->reservedPropertyNames,
                EntityType::authorizableRole => new AuthorizableRoleCodeGenerator($accessControlGenerator)->reservedPropertyNames,
                EntityType::authorization => new AuthorizationCodeGenerator($accessControlGenerator)->reservedPropertyNames,
                EntityType::none => [],
            };
            $existing = new Set($entity->attributesByName->keys)->union($entity->relationshipsByName->keys);
            return $this->missingReservedProperties = new Set($reservedPropertyNames)->subtracting($existing);
        }
    }
    /** @var Set<Role> */
    #[Outlet]
    private(set) Set $roles {
        get => $this->roles ??= $this->project->model?->roles ?? new Set();
    }
    /** @var Set<string> */
    #[Outlet]
    private(set) Set $defaultRoles {
        get => $this->defaultRoles ??= $this->roles->map(fn(Role $role): string => $role->name);
    }
    /** @var Set<string> */
    #[Outlet]
    private(set) Set $allRoles {
        get {
            if (isset($this->allRoles)) {
                return $this->allRoles;
            }
            $allRoles = $this->defaultRoles->map(fn(string $name): string => $name);
            $allRoles->insert("Custom...");
            return $this->allRoles = $allRoles;
        }
    }
    #[Outlet]
    private(set) bool $isCustomRole {
        get => $this->isCustomRole ??= $this->selectedRole !== null && !$this->defaultRoles->contains(fn(string $name): bool => $name === $this->selectedRole?->name);
    }
    #[Outlet]
    private(set) int $roleUsageCount {
        get => $this->roleUsageCount ??= $this->selectedRole?->accessControls->count ?? 0;
    }
    private ModelDescriptor $descriptor {
        get => $this->descriptor ??= new ModelDescriptor(new ModelSchemaExtractor($this->managedObjectContext, new AttributeSchemaFactory()), new VocabularyRepository(), new SchemaLocalizer(), new PredicateGuideFactory());
    }
    private ToolRegistry $registry {
        get => $this->registry ??= new ToolRegistry(new ToolResolver($this->managedObjectContext, $this->descriptor)->resolve());
    }
    /**
     * @var LLMExecutionPolicy Aprueba las escrituras con las que el editor hace su trabajo.
     *
     * Editar el modelo es lo que se le pide al asistente, y una entidad sin sus relaciones es un
     * modelo a medias, no un punto donde parar: por eso las instrucciones del servidor le mandan
     * completar la operación entera sin pedir confirmación a mitad. La aprobación se enumera por
     * nombre en lugar de aceptar cualquier escritura, para que una tool que se añada al catálogo
     * más adelante llegue denegada y se decida entonces si entra.
     *
     * Nada de esto escribe en un store de producción: los artefactos son el diseño.
     */
    private LLMExecutionPolicy $executionPolicy {
        get => $this->executionPolicy ??= new LLMExecutionPolicy(writeApproval: fn(LLMToolCall $toolCall): bool => new ArrayClass(["create", "update", "delete", "save_project", "generate_subclasses"])->containsElement($toolCall->name));
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function viewWillLoad(): void
    {
        if ($this->request->parameters["partial"] === "1") {
            $this->name = "EditorSelection";
        }
        $project = $this->project;
        $model = $project->model ?? throw new NotFoundException("Model not found");
        $this->breadcrumb->append($project);
        $this->breadcrumb->append($model);
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
            if (!($selection = $this->fetchByReference($managedObjectClass, $objectID))) {
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
            $this->breadcrumb->append($selection);
        }
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function save(): void
    {
        $project = $this->project;
        $updater = new BundleUpdater($project);
        $transaction = new SaveBundleTransaction($updater);
        $transaction->execute();
        $this->managedObjectContext->save();
        $this->data = $project;
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function import(): void
    {
        $parameters = $this->request->parameters;
        /** @var string $path */
        $path = $parameters["path"] ?? throw new BadRequestException("`path` is required");
        $project = $this->project;
        $model = $project->model ?? throw new InternalServerErrorException("Project model is missing");
        $fileManager = FileManager::default();
        $fileManager->fileExists($path) ?: throw new BadRequestException("File $path does not exist");
        $data = $fileManager->contents($path) ?? throw new InternalServerErrorException("Unable to read model file at `$path`");
        $managedObjectModel = KeyedUnarchiver::unarchiveTopLevelObjectWithData($data);
        $managedObjectModel instanceof ManagedObjectModel ?: throw new InternalServerErrorException("Imported file does not contain a valid ManagedObjectModel");
        $model->managedObjectModel = $managedObjectModel;
        $model->url = URL::fileURL($path);
        $project->model = $model;
        $this->data = $project;
    }

    /**
     * Freezes the model as it stands and opens the next version for editing.
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function version(): void
    {
        $project = $this->project;
        $transaction = new NewModelVersionTransaction($project);
        $transaction->execute();
        $this->managedObjectContext->save();
        $this->data = new Dictionary([
            "project" => $project,
            "frozenVersionName" => $transaction->frozenVersionName,
            "currentVersionName" => $transaction->currentVersionName,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function subclass(): void
    {
        $transaction = new SubclassTransaction($this->project);
        $transaction->execute();
        $this->save();
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function localize(): void
    {
        $languages = $this->request->parameters["languages"] ?? new ArrayClass(["en", "es"]);
        $transaction = new ExtractLocalizablesTransaction($this->project, new ArrayClass($languages));
        $transaction->execute();
        $this->data = $this->project;
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function reorder(): void
    {
        $parameters = $this->request->parameters;
        /** @var int $fromIndex */
        $fromIndex = $parameters["fromIndex"] ?? throw new BadRequestException("`fromIndex` is required");
        /** @var int $toIndex */
        $toIndex = $parameters["toIndex"] ?? throw new BadRequestException("`toIndex` is required");
        /** @var string $key */
        $key = $parameters["key"] ?? throw new BadRequestException("`key` is required");
        /** @var string $name */
        $name = $parameters["entity"] ?? throw new BadRequestException("`entity` is required");
        $model = $this->project->model;
        /** @var Entity $entity */
        $entity = $model->entitiesByName[$name] ?? throw new NotFoundException("Entity `$name` was not found");
        if ($fromIndex === $toIndex) {
            $this->data = $entity;
            return;
        }
        /** @var ArrayClass<Property> $subset */
        $subset = $entity->valueForKey($key);
        $subset = $subset->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position")]);
        /** @var Property $moved */
        $moved = $subset[$fromIndex];
        /** @var Property $target */
        $target = $subset[$toIndex];
        /** @var ArrayClass<Property> $properties */
        $properties = $entity->attributes->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position")]);
        $properties->appendContentsOf($entity->relationships->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position")]));
        $properties->appendContentsOf($entity->fetchedProperties->map(fn(Property $property): Property => $property)->sorted([new SortDescriptor("position")]));
        $properties->remove($moved);
        /** @var int<0, max> $globalToIndex */
        $globalToIndex = $properties->indexOf($target);
        if ($toIndex > $fromIndex) {
            $globalToIndex += 1;
        }
        $properties->insertAt($moved, $globalToIndex);
        $properties = $properties->map(function (Property $property, int $idx): Property {
            $property->position = max($idx, 0);
            return $property;
        });
        $entity->properties = new Set($properties);
        $this->managedObjectContext->save();
        $this->data = $entity;
    }

    /**
     * @throws Exception
     */
    private function buildSystemPrompt(): string
    {
        $project = $this->project;
        $lines = new ArrayClass([
            new MCPInstructionsProvider()->build(),
            "",
            "## Current context",
            "",
            "Project: $project->name",
        ]);
        if ($model = $project->model) {
            $lines->append("Model: $model->name (objectID: $model->objectID)");
        }
        $entityRef = $this->referenceObject("entity");
        if ($entityRef && ($entity = $this->fetchByReference(Entity::class, $entityRef))) {
            $lines->append("");
            $lines->append("Selected entity: \"$entity->name\" (objectID: $entity->objectID)");
            if (!$entity->attributes->isEmpty) {
                $attributes = $entity->attributes->map(fn(Attribute $attribute): string => "$attribute->name (objectID: $attribute->objectID, type: {$attribute->type->name}, " . ($attribute->isOptional ? "optional" : "required") . ")")->join(", ");
                $lines->append("  Attributes: $attributes");
            }
            if (!$entity->relationships->isEmpty) {
                $relationships = $entity->relationships->map(fn(Relationship $relationship): string => "$relationship->name (objectID: $relationship->objectID, " . ($relationship->isToMany ? "to-many" : "to-one") . " → $relationship->lazyDestinationEntityName)")->join(", ");
                $lines->append("  Relationships: $relationships");
            }
            if ($entity->superentity) {
                $lines->append("  Parent entity: {$entity->superentity->name} (objectID: {$entity->superentity->objectID})");
            }
        }
        $propertyRef = $this->referenceObject("property");
        if ($propertyRef && ($property = $this->fetchByReference(Property::class, $propertyRef))) {
            $lines->append("");
            $detail = match (true) {
                $property instanceof Attribute => "Attribute, type: {$property->type->name}",
                $property instanceof Relationship => "Relationship, " . ($property->isToMany ? "to-many" : "to-one") . " → $property->lazyDestinationEntityName",
                default => "Property",
            };
            $optional = $property->isOptional ? "optional" : "required";
            $lines->append("Selected property: \"$property->name\" (objectID: $property->objectID) — $detail, $optional");
        }
        $lines->append("");
        $lines->append("When the user asks questions or requests changes, assume they refer to the selected context unless otherwise specified.");
        return $lines->join("\n");
    }

    /**
     * @throws Throwable
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function chat(): void
    {
        $project = $this->project;
        $parameters = $this->request->parameters;
        /** @var string $content */
        $content = $parameters["content"] ?? throw new BadRequestException("`content` is required");
        /** @var string $model */
        $model = $parameters["model"] ?? UserDefaults::standard()->string(LLMModelPreferencesKey) ?? throw new BadRequestException("No model configured.");
        /** @var string $providerID */
        $providerID = $parameters["provider"] ?? UserDefaults::standard()->string(LLMProviderPreferencesKey) ?? throw new BadRequestException("No provider configured.");
        /** @var Dictionary<mixed> $snapshot */
        $snapshot = $parameters["conversation"] ?? throw new BadRequestException("`conversation` is required");
        $conversation = new Conversation($this->managedObjectContext);
        $conversation->updateFromSnapshot($snapshot);
        $conversation->provider = $providerID;
        $userMessage = new Message($this->managedObjectContext);
        $userMessage->role = LLMMessageRole::user;
        $userMessage->content = $content;
        $images = $parameters["images"];
        if ($images instanceof ArrayClass) {
            $fileManager = FileManager::default();
            $attachmentDirectory = $fileManager->url(SearchPathDirectory::applicationSupportDirectory)->appendingPathComponent("Singularity")->appendingPathComponent("Attachments");
            if (!$fileManager->fileExists($attachmentDirectory->path)) {
                $fileManager->createDirectory($attachmentDirectory, true);
            }
            foreach ($images as $image) {
                $extension = URLFileTypeMappings::shared()->preferredExtension((string)$image["mimeType"]) ?? "bin";
                $fileURL = $attachmentDirectory->appendingPathComponent(bin2hex(random_bytes(16)) . "." . $extension);
                $fileManager->createFile($fileURL->path, base64_decode((string)$image["data"]));
                $attachment = new Attachment($this->managedObjectContext);
                $attachment->name = (string)$image["name"];
                $attachment->url = $fileURL;
                $userMessage->addAttachmentsObject($attachment);
            }
        }
        $conversation->addMessagesObject($userMessage);
        /** @var ArrayClass<LLMMessage> $history */
        $history = new ArrayClass();
        foreach ($conversation->messages as $message) {
            if ($message === $userMessage) {
                $history->append(new LLMMessage(LLMMessageRole::user, $content, images: $images));
                continue;
            }
            $history->append($message->LLMMessage);
            foreach ($message->toolCalls as $toolCall) {
                if ($toolCall->result !== null) {
                    $history->append(new LLMMessage(LLMMessageRole::tool, $toolCall->result, toolCallId: $toolCall->identifier, isError: $toolCall->status === ToolCallStatus::error));
                }
            }
        }
        $provider = Provider::find($providerID) ?? throw new InternalServerErrorException("Provider `$providerID` not configured");
        $agent = new LLMAgent($provider->client($model), $this->registry, executionPolicy: $this->executionPolicy);
        $run = $agent->run($history, $this->buildSystemPrompt());
        /** @var array<string, ToolCall> $toolCallMap */
        $toolCallMap = [];
        foreach ($run->messages as $llmMessage) {
            if (($llmMessage->role === LLMMessageRole::tool) && ($id = $llmMessage->toolCallId) && isset($toolCallMap[$id])) {
                $toolCallMap[$id]->result = $llmMessage->content;
                $toolCallMap[$id]->status = $llmMessage->isError ? ToolCallStatus::error : ToolCallStatus::completed;
                continue;
            }
            $message = new Message($this->managedObjectContext);
            $message->LLMMessage = $llmMessage;
            foreach ($message->toolCalls as $toolCall) {
                $toolCallMap[$toolCall->identifier] = $toolCall;
            }
            $conversation->addMessagesObject($message);
        }
        $conversation->inputTokens += $run->inputTokens;
        $conversation->outputTokens += $run->outputTokens;
        $conversation->totalTokens = $conversation->inputTokens + $conversation->outputTokens;
        $project->addConversationsObject($conversation);
        $project->selectedConversation = $conversation;
        $this->managedObjectContext->save();
        $this->data = $conversation->dictionaryRepresentation;
    }
}
