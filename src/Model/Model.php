<?php

namespace App\Model;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Progress;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;

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
class Model extends ManagedObject
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
            /** @var ArrayClass<Dictionary>|null $attributes */
            $attributes = $dictionary["attributes"];
            if ($attributes) {
                $properties->appendContentsOf($attributes->map(function (Dictionary $description) use ($context, $entity): Attribute {
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
            /** @var ArrayClass<Dictionary>|null $relationships */
            $relationships = $dictionary["relationships"];
            if ($relationships) {
                $properties->appendContentsOf($relationships->map(function (Dictionary $description) use ($context, $entity): Relationship {
                    $relationship = new Relationship($context);
                    $relationship->entityProperty = $entity;
                    $relationship->setValuesForKeys($description);
                    return $relationship;
                }));
            }
            /** @var ArrayClass<Dictionary>|null $fetchedProperties */
            $fetchedProperties = $dictionary["fetchedProperties"];
            if ($fetchedProperties) {
                $properties->appendContentsOf($fetchedProperties->map(function (Dictionary $description) use ($context, $entity): FetchedProperty {
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
            /** @var ArrayClass<Dictionary>|null $subentities */
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
            /** @var ArrayClass<Dictionary> $indexes */
            $indexes = $dictionary["indexes"] ?? new ArrayClass();
            $entity->indexes = new Set($indexes->map(function (Dictionary $description) use ($context, $entity): FetchIndex {
                /** @var ArrayClass<Dictionary> $elements */
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
        $propertyList = PropertyListSerialization::propertyListWithURL($url);
        if (!$propertyList instanceof Dictionary) {
            return;
        }
        /** @var ArrayClass<Dictionary>|null $representations */
        $representations = $propertyList["entities"];
        if ($representations) {
            /** @var Set<Entity> $entities */
            $entities = new Set();
            $progress->totalUnitCount = $representations->count;
            foreach ($representations as $index => $representation) {
                if ($progress->isCancelled) {
                    break;
                }
                $entities->append($this->newEntity($representation));
                $progress->completedUnitCount = $index + 1;
            }
            $this->entities = $entities->sort(fn(Entity $e1, Entity $e2): int => $e1->name <=> $e2->name);
        }
        $this->compositeTypes = new Set($this->compositeTypesByName->values);
        /** @var ArrayClass<Dictionary>|null $representations */
        $representations = $propertyList["fetchRequests"];
        if ($representations) {
            $this->fetchRequestTemplates = new Set($representations->map(fn(Dictionary $representation): FetchRequestTemplate => $this->newFetchRequest($representation)));
        }
        $context->save();
    }
}
