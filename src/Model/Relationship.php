<?php

namespace App\Model;

use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;

/**
 * @property string $lazyDestinationEntityName
 * @property string $lazyInverseRelationshipName
 * @property bool $isToMany
 * @property bool $isOrdered
 * @property int<0, 3> $deleteRule
 * @property int|null $minCount
 * @property int|null $maxCount
 * @property bool $isMinCountBounded
 * @property bool $isMaxCountBounded
 */
class Relationship extends Property
{
    public readonly ?Entity $destinationEntity;
    public readonly ?Relationship $inverseRelationship;

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        unset($this->destinationEntity);
        unset($this->inverseRelationship);
    }

    public function __get(string $name)
    {
        if ($name == "destinationEntity") {
            $destinationEntity = null;
            if ($lazyDestinationEntityName = $this->lazyDestinationEntityName) {
                $destinationEntity = $this->entityProperty->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $lazyDestinationEntityName) ?? throw new InternalInconsistencyException(sprintf("%s, destination entity \"%s\" does not exists", $this->name, $lazyDestinationEntityName));
            }
            $this->$name = $destinationEntity;
            return $this->$name;
        } elseif ($name == "inverseRelationship") {
            $this->$name = $this->destinationEntity?->relationships?->first(fn(Relationship $relationship): bool => $relationship->name === $this->lazyInverseRelationshipName);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == "destinationEntity" || $name == "inverseRelationship") {
            $this->$name = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    public function dictionaryRepresentation(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = parent::dictionaryRepresentation();
        if ($isToMany = $this->isToMany) {
            $dictionary["isToMany"] = $isToMany;
        }
        if ($isOrdered = $this->isOrdered) {
            $dictionary["isOrdered"] = $isOrdered;
        }
        $deleteRule = DeleteRule::from($this->deleteRule);
        if ($deleteRule !== DeleteRule::nullifyDeleteRule) {
            $dictionary["deleteRule"] = $deleteRule->value;
        }
        $maxCount = $this->maxCount;
        if ($maxCount !== null) {
            $dictionary["maxCount"] = $maxCount;
        }
        $minCount = $this->minCount;
        if ($minCount !== null) {
            $dictionary["minCount"] = $minCount;
        }
        $dictionary["lazyDestinationEntityName"] = $this->lazyDestinationEntityName;
        $dictionary["lazyInverseRelationshipName"] = $this->lazyInverseRelationshipName;
        return $dictionary;
    }
}
