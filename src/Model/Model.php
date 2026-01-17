<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\SQLColumn;
use Sabatier\CoreData\SQLEntity;
use Sabatier\CoreData\SQLForeignKey;
use Sabatier\CoreData\SQLManyToMany;
use Sabatier\CoreData\SQLModel;
use Sabatier\CoreData\SQLPrimaryKey;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Progress;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\fatal_error;
use const App\EntityPositionsMappingPreferencesKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * @property URL|null $url
 * @property Project|null $project
 * @property Set<Entity> $entities
 * @property Set<FetchRequestTemplate> $fetchRequestTemplates
 * @property Set<Configuration> $configurations
 * @property Set<CompositeType> $compositeTypes
 * @method void addEntitiesObject(Entity $object)
 * @method void removeEntitiesObject(Entity $object)
 * @method void addEntities(Set $objects)
 * @method void removeEntities(Set $objects)
 * @method Set<Entity> intersectEntities(Set $objects)
 * @method void setEntities(Set $objects)
 * @method void addFetchRequestTemplatesObject(FetchRequestTemplate $object)
 * @method void removeFetchRequestTemplatesObject(FetchRequestTemplate $object)
 * @method void addFetchRequestTemplates(Set $objects)
 * @method void removeFetchRequestTemplates(Set $objects)
 * @method Set<FetchRequestTemplate> intersectFetchRequestTemplates(Set $objects)
 * @method void setFetchRequestTemplates(Set $objects)
 * @method void addConfigurationsObject(Configuration $object)
 * @method void removeConfigurationsObject(Configuration $object)
 * @method void addConfigurations(Set $objects)
 * @method void removeConfigurations(Set $objects)
 * @method Set<Configuration> intersectConfigurations(Set $objects)
 * @method void setConfigurations(Set $objects)
 * @method void addCompositeTypesObject(CompositeType $object)
 * @method void removeCompositeTypesObject(CompositeType $object)
 * @method void addCompositeTypes(Set $objects)
 * @method void removeCompositeTypes(Set $objects)
 * @method Set<CompositeType> intersectCompositeTypes(Set $objects)
 * @method void setCompositeTypes(Set $objects)
 */
final class Model extends ManagedObject
{
    /** @var Set<Entity> */
    private(set) Set $rootEntities {
        get => $this->rootEntities ??= $this->entities->filter(fn(Entity $entity): bool => $entity->isRootEntity);
    }
    /** @var Dictionary<Entity> */
    private(set) Dictionary $entitiesByName {
        get => $this->entitiesByName ??= $this->entities->reduce(new Dictionary(), function (Dictionary $result, Entity $entity): Dictionary {
            $result[$entity->name] = $entity;
            return $result;
        });
    }
    /** @var Dictionary<CompositeType> */
    private(set) Dictionary $compositeTypesByName {
        get => $this->compositeTypesByName ??= $this->compositeTypes->reduce(new Dictionary(), function (Dictionary $result, CompositeType $compositeType): Dictionary {
            $result[$compositeType->name] = $compositeType;
            return $result;
        });
    }
    private(set) Progress $progress {
        get => $this->progress ??= new Progress();
    }
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["entities"] = $this->rootEntities->map(fn(Entity $entity): Dictionary => $entity->dictionaryRepresentation);
            $dictionary["fetchRequests"] = $this->fetchRequestTemplates->map(fn(FetchRequestTemplate $fetchRequestTemplate): Dictionary => $fetchRequestTemplate->dictionaryRepresentation);
            return $dictionary;
        }
    }
    public Dictionary $schema {
        get {
            /** @var Project $project */
            $project = $this->project;
            /** @var URL $url */
            $url = $project->url;
            $name = $project->name;
            $bundle = Bundle::bundleWithURL($url);
            $autoloadPath = $bundle->bundleURL->appendingPathComponent("vendor")->appendingPathComponent("autoload")->appendingPathExtension("php")->path;
            if (FileManager::default()->fileExists($autoloadPath)) {
                require_once $autoloadPath;
            }
            /** @var string $configurationName */
            $configurationName = $bundle->object(kCFBundleNameKey);
            $managedObjectModel = new ManagedObjectModel($bundle->url($configurationName));
            $model = new SQLModel($managedObjectModel, $configurationName);
            $entities = $model->entities->filter(fn(SQLEntity $entity): bool => $entity->isRootEntity && !$entity->isPersistentHistoryEntity);
            $tables = $entities->map(fn(SQLEntity $entity): Dictionary => new Dictionary(["name" => $entity->tableName, "columns" => $entity->columnsToCreate->map(function (SQLColumn $column): Dictionary {
                /** @var Dictionary<mixed> $dictionary */
                $dictionary = new Dictionary(["name" => $column->columnName, "type" => $column->sqlType->name]);
                if ($column instanceof SQLPrimaryKey) {
                    $dictionary["pk"] = true;
                } elseif ($column instanceof SQLForeignKey) {
                    $dictionary["fk"] = new Dictionary(["table" => $column->toOneRelationship->destinationEntity->tableName, "column" => $column->toOneRelationship->destinationEntity->primaryKey->columnName]);
                } else {
                    $dictionary["nn"] = !$column->isOptional;
                    $dictionary["uq"] = $column->isUnique;
                }
                return $dictionary;
            }), "pos" => UserDefaults::standard()->dictionary(EntityPositionsMappingPreferencesKey)?->valueForKey($name)?->valueForKey($entity->tableName)]));
            $tables->appendContentsOf(new Set($entities)->flatMap(fn(SQLEntity $entity): ArrayClass => $entity->manyToManyRelationships)->map(fn(SQLManyToMany $manyToMany): Dictionary => new Dictionary(["name" => $manyToMany->correlationTableName, "columns" => new ArrayClass([new Dictionary(["name" => $manyToMany->orderColumnName, "type" => $manyToMany->columnSQLType->name, "pk" => true, "fk" => new Dictionary(["table" => $manyToMany->entities[0]->tableName, "column" => $manyToMany->entities[0]->primaryKey->columnName])]), new Dictionary(["name" => $manyToMany->inverseOrderColumnName, "type" => $manyToMany->columnSQLType->name, "pk" => true, "fk" => new Dictionary(["table" => $manyToMany->entities[1]->tableName, "column" => $manyToMany->entities[1]->primaryKey->columnName])])]), "pos" => UserDefaults::standard()->dictionary(EntityPositionsMappingPreferencesKey)?->valueForKey($name)?->valueForKey($manyToMany->correlationTableName)])));
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["tables"] = $tables;
            return $dictionary;
        }
    }

    public Dictionary $graph {
        get {
            $nodes = [];
            $edges = [];
            $processEntity = function (Entity $entity, ?string $parentName = null) use (&$nodes, &$edges, &$processEntity): void {
                $type = $entity->isAbstract ? "abstract" : ($entity->isFinal ? "final" : "normal");
                $classes = $entity->isAbstract ? "abstract-entity" : ($entity->isFinal ? "final-entity" : "");
                $attributesData = [];
                foreach ($entity->attributes as $attribute) {
                    $attributesData[] = [
                        "name" => $attribute->name,
                        "type" => $attribute->type->name,
                        "isOptional" => $attribute->isOptional,
                        "isTransient" => $attribute->isTransient,
                        "defaultValue" => $attribute->defaultValue,
                        "isDerived" => $attribute->isDerived,
                        "reference" => $attribute->objectID->referenceObject,
                    ];
                }
                $relationshipsData = [];
                foreach ($entity->relationships as $relationship) {
                    $relationshipsData[] = [
                        "name" => $relationship->name,
                        "destination" => $relationship->lazyDestinationEntityName,
                        "isToMany" => $relationship->isToMany,
                        "isOptional" => $relationship->isOptional,
                        "deleteRule" => $relationship->deleteRule->name,
                    ];
                }
                $nodes[] = [
                    "data" => [
                        "id" => $entity->name,
                        "label" => $entity->name,
                        "type" => $type,
                        "attributes" => $attributesData,
                        "relationships" => $relationshipsData,
                        "attributeCount" => $entity->attributes->count,
                        "relationshipCount" => $entity->relationships->count,
                        "href" => "/Editor?project={$this->project?->objectID?->referenceObject}&entity={$entity->objectID->referenceObject}"
                    ],
                    "classes" => $classes
                ];
                if ($parentName !== null) {
                    $edges[] = [
                        "data" => [
                            "id" => "$entity->name-inherits-$parentName",
                            "source" => $entity->name,
                            "target" => $parentName,
                            "type" => "inheritance",
                            "label" => "inherits"
                        ],
                        "classes" => "inheritance-edge"
                    ];
                }
                foreach ($entity->relationships as $relationship) {
                    $destEntity = $relationship->destinationEntity?->name;
                    if (!$destEntity) {
                        continue;
                    }
                    $edgeId = "$entity->name-$relationship->name-$destEntity";
                    $inverseRel = $relationship->inverseRelationship?->name ?? "";
                    $reverseEdgeId = "$destEntity-$inverseRel-$entity->name";
                    $exists = false;
                    foreach ($edges as $edge) {
                        if ($edge["data"]["id"] === $reverseEdgeId) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $edges[] = [
                            "data" => [
                                "id" => $edgeId,
                                "source" => $entity->name,
                                "target" => $destEntity,
                                "type" => "relationship",
                                "label" => $relationship->name,
                                "sourceLabel" => $relationship->name,
                                "targetLabel" => $inverseRel,
                                "isToMany" => $relationship->isToMany,
                                "inverseIsToMany" => $relationship->inverseRelationship?->isToMany ?? false,
                            ],
                            "classes" => "relationship-edge"
                        ];
                    }
                }
                foreach ($entity->subentities as $subentity) {
                    $processEntity($subentity, $entity->name);
                }
            };
            foreach ($this->rootEntities as $entity) {
                $processEntity($entity);
            }
            return Dictionary::dictionaryWithArray([
                "nodes" => $nodes,
                "edges" => $edges
            ]);
        }
    }

    private function newEntity(Dictionary $dictionary): Entity
    {
        $context = $this->managedObjectContext;
        /** @var string $name */
        $name = $dictionary["name"] ?? fatal_error("Invalid argument, entity name cannot be null");
        $entity = $this->entitiesByName[$name];
        if (!$entity instanceof Entity) {
            $entity = new Entity($context);
            $entity->name = $name;
            $entity->managedObjectClassName = $dictionary["managedObjectClassName"];
            if ($isAbstract = $dictionary["isAbstract"]) {
                $entity->isAbstract = $isAbstract;
            }
            $entity->renamingIdentifier = $dictionary["renamingIdentifier"];
            /** @var Set<Property> $properties */
            $properties = new Set();
            /** @var ArrayClass<Dictionary<mixed>>|null $attributes */
            $attributes = $dictionary["attributes"];
            if ($attributes) {
                $properties->formUnion($attributes->map(function (Dictionary $description) use ($context, $entity): Attribute {
                    $attribute = new Attribute($context);
                    $attribute->entityProperty = $entity;
                    $attribute->isDerived = !empty($description["derivationExpressionFormat"]);
                    if ($description["elements"]) {
                        if (($attributeValueClassName = $description["attributeValueClassName"]) && !$this->compositeTypesByName[$attributeValueClassName]) {
                            /** @var ArrayClass<Dictionary<mixed>> $elements */
                            $elements = $description["elements"];
                            $compositeType = new CompositeType($context);
                            $compositeType->name = $attributeValueClassName;
                            $compositeType->elements = new Set($elements->map(function (Dictionary $element): Attribute {
                                $attribute = new Attribute($this->managedObjectContext);
                                $attribute->setValuesForKeys($element);
                                return $attribute;
                            }));
                            $this->compositeTypesByName[$attributeValueClassName] = $compositeType;
                        }
                        $description->removeValueForKey("elements");
                    }
                    $attribute->setValuesForKeys($description);
                    return $attribute;
                }));
            }
            /** @var ArrayClass<Dictionary<mixed>>|null $relationships */
            $relationships = $dictionary["relationships"];
            if ($relationships) {
                $properties->formUnion($relationships->map(function (Dictionary $description) use ($context, $entity): Relationship {
                    $relationship = new Relationship($context);
                    $relationship->entityProperty = $entity;
                    $relationship->setValuesForKeys($description);
                    return $relationship;
                }));
            }
            /** @var ArrayClass<Dictionary<mixed>>|null $fetchedProperties */
            $fetchedProperties = $dictionary["fetchedProperties"];
            if ($fetchedProperties) {
                $properties->formUnion($fetchedProperties->map(function (Dictionary $description) use ($context, $entity): FetchedProperty {
                    $fetchedProperty = new FetchedProperty($context);
                    $fetchedProperty->entityProperty = $entity;
                    $fetchedProperty->setValuesForKeys($description);
                    return $fetchedProperty;
                }));
            }
            $entity->properties = $properties;
            /** @var Dictionary|null $superentity */
            $superentity = $dictionary["superentity"];
            if ($superentity) {
                $entity->superentity = $this->newEntity($superentity);
            }
            /** @var ArrayClass<Dictionary<mixed>>|null $subentities */
            $subentities = $dictionary["subentities"];
            if ($subentities) {
                $entity->subentities = new Set($subentities->map(function (Dictionary $description) use ($entity): Entity {
                    $subentity = $this->newEntity($description);
                    $subentity->superentity = $entity;
                    return $subentity;
                }));
            }
            /** @var ArrayClass<ArrayClass<string>> $uniquenessConstraints */
            $uniquenessConstraints = $dictionary["uniquenessConstraints"] ?? new ArrayClass();
            $entity->uniquenessConstraints = new Set($uniquenessConstraints->map(function (ArrayClass $array) use ($context): UniquenessConstraint {
                $uniquenessConstraint = new UniquenessConstraint($context);
                $uniquenessConstraint->stringValue = $array->join(",");
                return $uniquenessConstraint;
            }));
            /** @var ArrayClass<Dictionary<mixed>> $indexes */
            $indexes = $dictionary["indexes"] ?? new ArrayClass();
            $entity->indexes = new Set($indexes->map(function (Dictionary $description) use ($context, $entity): FetchIndex {
                /** @var ArrayClass<Dictionary<mixed>> $elements */
                $elements = $description["elements"] ?? new ArrayClass();
                $fetchIndex = new FetchIndex($context);
                $fetchIndex->name = $description["name"];
                $fetchIndex->entityProperty = $entity;
                $fetchIndex->elements = new Set($elements->map(function (Dictionary $description) use ($context): FetchIndexElement {
                    $fetchIndexElement = new FetchIndexElement($context);
                    $fetchIndexElement->setValuesForKeys($description);
                    return $fetchIndexElement;
                }));
                return $fetchIndex;
            }));
            $entity->model = $this;
            $this->entitiesByName[$name] = $entity;
        }
        return $entity;
    }

    private function newFetchRequest(Dictionary $dictionary): FetchRequestTemplate
    {
        $fetchRequest = new FetchRequestTemplate($this->managedObjectContext);
        $fetchRequest->setValuesForKeys($dictionary);
        return $fetchRequest;
    }

    /**
     * @throws Exception
     */
    public function load(URL $url): void
    {
        $propertyList = PropertyListSerialization::propertyListWithURL($url);
        if (!$propertyList instanceof Dictionary) {
            return;
        }
        $context = $this->managedObjectContext;
        $entities = $this->entities;
        $progress = $this->progress;
        if (!$entities->isEmpty) {
            foreach ($entities as $entity) {
                if ($entity->isRootEntity) {
                    $context->delete($entity);
                }
            }
            $context->save();
        }
        $fetchRequestTemplates = $this->fetchRequestTemplates;
        if (!$fetchRequestTemplates->isEmpty) {
            foreach ($fetchRequestTemplates as $fetchRequestTemplate) {
                $context->delete($fetchRequestTemplate);
            }
            $context->save();
        }
        /** @var ArrayClass<Dictionary<mixed>>|null $representations */
        $representations = $propertyList["entities"];
        if ($representations) {
            /** @var Set<Entity> $entities */
            $entities = new Set();
            $progress->totalUnitCount = $representations->count;
            foreach ($representations as $index => $representation) {
                if ($progress->isCancelled) {
                    break;
                }
                $entities->insert($this->newEntity($representation));
                $progress->completedUnitCount = $index + 1;
            }
            $this->entities = $entities->sort(fn(Entity $e1, Entity $e2): int => $e1->name <=> $e2->name);
        }
        $this->compositeTypes = new Set($this->compositeTypesByName->values);
        /** @var ArrayClass<Dictionary<mixed>>|null $representations */
        $representations = $propertyList["fetchRequests"];
        if ($representations) {
            $this->fetchRequestTemplates = new Set($representations->map($this->newFetchRequest(...)));
        }
        $context->save();
    }
}
