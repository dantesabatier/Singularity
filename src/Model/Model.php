<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace App\Model;

use Exception;
use Override;
use Sabatier\CoreData\AttributeDescription;
use Sabatier\CoreData\CompositeAttributeDescription;
use Sabatier\CoreData\DerivedAttributeDescription;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\RelationshipDescription;
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
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\EntityPositionsMappingPreferencesKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * @property string|null $name
 * @property URL|null $url
 * @property float $zoom
 * @property Dictionary<float> $pan
 * @property Project|null $project
 * @property Set<Entity> $entities
 * @property Set<FetchRequestTemplate> $fetchRequestTemplates
 * @property Set<Configuration> $configurations
 * @property Set<CompositeType> $compositeTypes
 * @property Set<Role> $roles
 * @method void addEntitiesObject(Entity $object)
 * @method void removeEntitiesObject(Entity $object)
 * @method void addEntities(Set<Entity> $objects)
 * @method void removeEntities(Set<Entity> $objects)
 * @method Set<Entity> intersectEntities(Set<Entity> $objects)
 * @method void setEntities(Set<Entity> $objects)
 * @method void addFetchRequestTemplatesObject(FetchRequestTemplate $object)
 * @method void removeFetchRequestTemplatesObject(FetchRequestTemplate $object)
 * @method void addFetchRequestTemplates(Set<FetchRequestTemplate> $objects)
 * @method void removeFetchRequestTemplates(Set<FetchRequestTemplate> $objects)
 * @method Set<FetchRequestTemplate> intersectFetchRequestTemplates(Set<FetchRequestTemplate> $objects)
 * @method void setFetchRequestTemplates(Set<FetchRequestTemplate> $objects)
 * @method void addConfigurationsObject(Configuration $object)
 * @method void removeConfigurationsObject(Configuration $object)
 * @method void addConfigurations(Set<Configuration> $objects)
 * @method void removeConfigurations(Set<Configuration> $objects)
 * @method Set<Configuration> intersectConfigurations(Set<Configuration> $objects)
 * @method void setConfigurations(Set<Configuration> $objects)
 * @method void addCompositeTypesObject(CompositeType $object)
 * @method void removeCompositeTypesObject(CompositeType $object)
 * @method void addCompositeTypes(Set<CompositeType> $objects)
 * @method void removeCompositeTypes(Set<CompositeType> $objects)
 * @method Set<CompositeType> intersectCompositeTypes(Set<CompositeType> $objects)
 * @method void setCompositeTypes(Set<CompositeType> $objects)
 * @method void addRolesObject(Role $object)
 * @method void removeRolesObject(Role $object)
 * @method void addRoles(Set<Role> $objects)
 * @method void removeRoles(Set<Role> $objects)
 * @method Set<Role> intersectRoles(Set<Role> $objects)
 * @method void setRoles(Set<Role> $objects)
 */
final class Model extends ManagedObject
{
    /** @var Set<Entity> */
    private(set) Set $rootEntities {
        get => $this->rootEntities ??= $this->entities->filter(fn(Entity $entity): bool => $entity->isRootEntity);
    }
    /** @var Dictionary<Entity> */
    private(set) Dictionary $entitiesByName {
        get => $this->entitiesByName ??= $this->entities->reduce(new Dictionary(),
            /**
             * @param Dictionary<Entity> $result
             * @param Entity $entity
             * @return Dictionary<Entity>
             */
            function (Dictionary $result, Entity $entity): Dictionary {
                $result[$entity->name] = $entity;
                return $result;
            });
    }
    /** @var Dictionary<CompositeType> */
    private(set) Dictionary $compositeTypesByName {
        get => $this->compositeTypesByName ??= $this->compositeTypes->reduce(new Dictionary(),
            /**
             * @param Dictionary<CompositeType> $result
             * @param CompositeType $compositeType
             * @return Dictionary<CompositeType>
             */
            function (Dictionary $result, CompositeType $compositeType): Dictionary {
                $result[$compositeType->name] = $compositeType;
                return $result;
            });
    }
    /** @var Dictionary<mixed> */
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
    /** @var Dictionary<mixed> */
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
                $node = [
                    "data" => [
                        "id" => $entity->name,
                        "entityID" => $entity->objectID->referenceObject,
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
                $position = $entity->position;
                if (!empty($position["x"]) || !empty($position["y"])) {
                    $node["position"] = $position;
                }
                $nodes[] = $node;
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
                    $destinationEntityName = $relationship->destinationEntity?->name;
                    if (!$destinationEntityName) {
                        continue;
                    }
                    $edgeId = "$entity->name-$relationship->name-$destinationEntityName";
                    $inverseRelationshipName = $relationship->inverseRelationship?->name ?? "";
                    $inverseEdgeId = "$destinationEntityName-$inverseRelationshipName-$entity->name";
                    $exists = array_any($edges, fn($edge) => $edge["data"]["id"] === $inverseEdgeId);
                    if (!$exists) {
                        $edges[] = [
                            "data" => [
                                "id" => $edgeId,
                                "source" => $entity->name,
                                "target" => $destinationEntityName,
                                "type" => "relationship",
                                "label" => $relationship->name,
                                "sourceLabel" => $relationship->name,
                                "targetLabel" => $inverseRelationshipName,
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
                "id" => $this->objectID->referenceObject,
                "name" => $this->name,
                "zoom" => $this->zoom,
                "pan" => $this->pan,
                "nodes" => $nodes,
                "edges" => $edges
            ]);
        }
    }
    public ManagedObjectModel $managedObjectModel {
        get {
            if (isset($this->managedObjectModel)) {
                return $this->managedObjectModel;
            }
            $managedObjectModel = new ManagedObjectModel();
            $managedObjectModel->entities = new ArrayClass($this->rootEntities->map(fn(Entity $entity) => $entity->entityDescription));
            $this->fetchRequestTemplates->forEach(function (FetchRequestTemplate $fetchRequestTemplate) use ($managedObjectModel) {
                if ($fetchRequest = $fetchRequestTemplate->fetchRequest) {
                    $managedObjectModel->setFetchRequestTemplate($fetchRequest, $fetchRequestTemplate->name);
                }
            });
            $this->configurations->forEach(function (Configuration $configuration) use ($managedObjectModel) {
                $managedObjectModel->setEntities(new ArrayClass($configuration->entities), $configuration->name);
            });
            return $this->managedObjectModel = $managedObjectModel;
        }
        /**
         * @throws Exception
         */
        set {
            $this->managedObjectModel = $value;
            $context = $this->managedObjectContext;
            $this->entities->forEach(fn(Entity $entity) => $context->delete($entity));
            $this->fetchRequestTemplates->forEach(fn(FetchRequestTemplate $template) => $context->delete($template));
            $this->configurations->forEach(fn(Configuration $configuration) => $context->delete($configuration));
            $this->compositeTypes->forEach(fn(CompositeType $compositeType) => $context->delete($compositeType));
            if ($context->hasChanges) {
                $context->save();
            }
            /** @var Dictionary<Entity> $entityMap */
            $entityMap = new Dictionary();
            /** @var Dictionary<CompositeType> $compositeTypeMap */
            $compositeTypeMap = new Dictionary();
            foreach ($this->managedObjectModel->entities as $entityDescription) {
                $entity = new Entity($context);
                $entity->name = $entityDescription->name;
                $entity->managedObjectClassName = $entityDescription->managedObjectClassName;
                $entity->renamingIdentifier = $entityDescription->renamingIdentifier;
                $entity->versionHashModifier = $entityDescription->versionHashModifier;
                $entity->isAbstract = $entityDescription->isAbstract;
                $this->addEntitiesObject($entity);
                $entityMap[$entityDescription->name] = $entity;
            }
            foreach ($this->managedObjectModel->entities as $entityDescription) {
                $entity = $entityMap[$entityDescription->name];
                if ($superName = $entityDescription->superentity?->name) {
                    $entity->superentity = $entityMap[$superName];
                }
                foreach ($entityDescription->properties as $propertyDescription) {
                    if ($propertyDescription instanceof CompositeAttributeDescription) {
                        $property = new Attribute($context);
                        $property->setValuesForKeys($propertyDescription->dictionaryWithValues($property->attributeDescriptionKeys));
                        if (!$compositeTypeMap->offsetExists($propertyDescription->name)) {
                            $compositeType = new CompositeType($context);
                            $compositeType->name = $propertyDescription->name;
                            foreach ($propertyDescription->elements as $elementDescription) {
                                $element = new Attribute($context);
                                $element->setValuesForKeys($elementDescription->dictionaryWithValues($element->attributeDescriptionKeys));
                                $compositeType->addElementsObject($element);
                            }
                            $this->addCompositeTypesObject($compositeType);
                            $compositeTypeMap[$propertyDescription->name] = $compositeType;
                        }
                        $property->compositeType = $compositeTypeMap[$propertyDescription->name];
                    } elseif ($propertyDescription instanceof AttributeDescription) {
                        $property = new Attribute($context);
                        $property->setValuesForKeys($propertyDescription->dictionaryWithValues($property->attributeDescriptionKeys));
                    } elseif ($propertyDescription instanceof RelationshipDescription) {
                        $property = new Relationship($context);
                        $property->setValuesForKeys($propertyDescription->dictionaryWithValues($property->relationshipDescriptionKeys));
                    } else {
                        $property = new FetchedProperty($context);
                        $property->setValuesForKeys($propertyDescription->dictionaryWithValues($property->fetchedPropertyDescriptionKeys));
                    }
                    $entity->addPropertiesObject($property);
                    if ($property instanceof Attribute && $propertyDescription instanceof DerivedAttributeDescription) {
                        $property->derivationExpressionFormat = $propertyDescription->derivationExpression?->predicateFormat;
                    }
                }
                /** @var ArrayClass<AttributeDescription|string> $uniquenessConstraints */
                foreach ($entityDescription->uniquenessConstraints as $uniquenessConstraints) {
                    $uniquenessConstraint = new UniquenessConstraint($context);
                    $uniquenessConstraint->stringValue = $uniquenessConstraints->map(fn(AttributeDescription|string $attribute): string => $attribute instanceof AttributeDescription ? $attribute->name : $attribute)->join(",");
                    $entity->addUniquenessConstraintsObject($uniquenessConstraint);
                }
                foreach ($entityDescription->indexes as $fetchIndexDescription) {
                    $index = new FetchIndex($context);
                    $index->name = $fetchIndexDescription->name;
                    $index->partialIndexPredicateFormat = $fetchIndexDescription->partialIndexPredicate?->predicateFormat;
                    $entity->addIndexesObject($index);
                    foreach ($fetchIndexDescription->elements as $fetchIndexElementDescription) {
                        $element = new FetchIndexElement($context);
                        $element->propertyName = $fetchIndexElementDescription->property->name;
                        $element->collationType = $fetchIndexElementDescription->collationType;
                        $element->isAscending = $fetchIndexElementDescription->isAscending;
                        if ($fetchIndexElementDescription->property instanceof ExpressionDescription) {
                            $element->expressionFormat = $fetchIndexElementDescription->property->expression?->predicateFormat;
                        }
                        $index->addElementsObject($element);
                    }
                }
            }
            foreach ($this->managedObjectModel->configurations as $configuration) {
                $config = new Configuration($context);
                $config->name = $configuration;
                $config->entities = new Set($this->managedObjectModel->entities($configuration)->map(fn(EntityDescription $entityDescription) => $entityMap[$entityDescription->name]));
                $this->addConfigurationsObject($config);
            }
            foreach ($this->managedObjectModel->fetchRequestTemplatesByName as $name => $fetchRequest) {
                $template = new FetchRequestTemplate($context);
                $template->name = $name;
                $template->fetchEntityName = $fetchRequest->entity->name;
                $template->predicateString = $fetchRequest->predicate?->predicateFormat;
                $template->fetchLimit = $fetchRequest->fetchLimit;
                $template->fetchBatchSize = $fetchRequest->fetchBatchSize;
                $template->fetchResultType = $fetchRequest->resultType;
                $template->includesSubentities = $fetchRequest->includesSubentities;
                $template->includesPropertyValues = $fetchRequest->includesPropertyValues;
                $template->returnsObjectsAsFaults = $fetchRequest->returnsObjectsAsFaults;
                $template->includesPendingChanges = $fetchRequest->includesPendingChanges;
                $template->returnsDistinctResults = $fetchRequest->returnsDistinctResults;
                $this->addFetchRequestTemplatesObject($template);
            }
            if ($context->hasChanges) {
                $context->save();
            }
        }
    }

    #[Override]
    public function awakeFromFetch(): void
    {
        $this->name = $this->project?->name;
    }
}
