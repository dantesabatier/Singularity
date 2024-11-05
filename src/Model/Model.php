<?php

namespace App\Model;

use Exception;
use InvalidArgumentException;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Progress;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;

/**
 * @property URL|null $url
 * @property Project|null $project
 * @property Set<Entity> $entities
 * @property Set<FetchRequestTemplate> $fetchRequestTemplates
 * @property Set<Configuration> $configurations
 * @property Set<CompositeAttribute> $compositeAttributes
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
 * @method void addCompositeAttributesObject(CompositeAttribute $object)
 * @method void removeCompositeAttributesObject(CompositeAttribute $object)
 * @method void addCompositeAttributes(Set $objects)
 * @method void removeCompositeAttributes(Set $objects)
 * @method Set<CompositeAttribute> intersectCompositeAttributes(Set $objects)
 * @method void setCompositeAttributes(Set $objects)
 */
class Model extends ManagedObject
{
    /** @var Dictionary<Entity> */
    public Dictionary $entitiesByName;
    public Progress $progress;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        unset($this->entitiesByName);
        unset($this->progress);
    }

    #[Override]
    public function __get(string $name)
    {
        if ($name === "entitiesByName") {
            $this->$name = new Dictionary();
            return $this->$name;
        }
        if ($name === "progress") {
            $this->$name = new Progress();
            return $this->$name;
        }
        return parent::__get($name);
    }

    private function newEntity(Dictionary $dictionary): Entity
    {
        $context = $this->managedObjectContext;
        /** @var string $name */
        $name = $dictionary["name"] ?? throw new InvalidArgumentException("Invalid argument, entity name cannot be null");
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
                    $instance = new Attribute($context);
                    $instance->entityProperty = $entity;
                    $instance->isDerived = !empty($description["derivationExpressionFormat"]);
                    $instance->setValuesForKeys($description);
                    return $instance;
                }));
            }
            /** @var ArrayClass<Dictionary>|null $relationships */
            $relationships = $dictionary["relationships"];
            if ($relationships) {
                $properties->appendContentsOf($relationships->map(function (Dictionary $description) use ($context, $entity): Relationship {
                    $instance = new Relationship($context);
                    $instance->entityProperty = $entity;
                    $instance->setValuesForKeys($description);
                    return $instance;
                }));
            }
            /** @var ArrayClass<Dictionary>|null $fetchedProperties */
            $fetchedProperties = $dictionary["fetchedProperties"];
            if ($fetchedProperties) {
                $properties->appendContentsOf($fetchedProperties->map(function (Dictionary $description) use ($context, $entity): FetchedProperty {
                    $instance = new FetchedProperty($context);
                    $instance->entityProperty = $entity;
                    $instance->setValuesForKeys($description);
                    return $instance;
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
                $index = new FetchIndex($context);
                $index->name = $description["name"];
                $index->entityProperty = $entity;
                $index->elements = new Set($elements->map(function (Dictionary $description) use ($context): FetchIndexElement {
                    $element = new FetchIndexElement($context);
                    $element->setValuesForKeys($description);
                    return $element;
                }));
                return $index;
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
        if ($propertyList instanceof Dictionary) {
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
                $this->entities = $entities;
            }
            /** @var ArrayClass<Dictionary>|null $representations */
            $representations = $propertyList["fetchRequests"];
            if ($representations) {
                $this->fetchRequestTemplates = new Set($representations->map(fn(Dictionary $representation): FetchRequestTemplate => $this->newFetchRequest($representation)));
            }
            $context->save();
        }
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary["entities"] = $this->entities->filter(fn(Entity $entity): bool => $entity->isRootEntity)->sort(fn(Entity $e1, Entity $e2): int => $e1->name <=> $e2->name)->map(fn(Entity $entity): Dictionary => $entity->dictionaryRepresentation());
        $dictionary["fetchRequests"] = $this->fetchRequestTemplates->map(fn(FetchRequestTemplate $fetchRequestTemplate): Dictionary => $fetchRequestTemplate->dictionaryRepresentation());
        $dictionary["compositeAttributes"] = $this->compositeAttributes->map(fn(CompositeAttribute $compositeAttribute): Dictionary => $compositeAttribute->dictionaryRepresentation());
        return $dictionary;
    }
}
