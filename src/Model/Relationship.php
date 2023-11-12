<?php

namespace App\Model;

use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservingOptions;

/**
 * @property string $lazyDestinationEntityName
 * @property string $lazyInverseRelationshipName
 * @property bool $isToMany
 * @property bool $isOrdered
 * @property DeleteRule $deleteRule
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

        $this->observe("isMinCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship): void {
            $relationship->minCount = $relationship->isMinCountBounded ? $this->minCount : null;
        });
        $this->observe("isMaxCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship): void {
            $relationship->maxCount = $relationship->isMaxCountBounded ? $this->maxCount : null;
        });
    }

    public function __get(string $name)
    {
        if ($name == "destinationEntity") {
            $destinationEntity = null;
            if ($lazyDestinationEntityName = $this->lazyDestinationEntityName) {
                $destinationEntity = $this->entityProperty->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $lazyDestinationEntityName);
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

    public function validateDeleteRule(DeleteRule|int|null &$deleteRule): bool
    {
        if (is_int($deleteRule)) {
            $deleteRule = DeleteRule::from($deleteRule);
        }
        return true;
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
        $deleteRule = $this->deleteRule;
        if ($deleteRule !== DeleteRule::nullifyDeleteRule) {
            $dictionary["deleteRule"] = $deleteRule->value;
        }
        $isMinCountBounded = $this->isMinCountBounded;
        if ($isMinCountBounded) {
            $dictionary["isMinCountBounded"] = $isMinCountBounded;
        }
        $isMaxCountBounded = $this->isMaxCountBounded;
        if ($isMaxCountBounded) {
            $dictionary["isMaxCountBounded"] = $isMaxCountBounded;
        }
        $dictionary["minCount"] = $isMinCountBounded ? $this->minCount : null;
        $dictionary["maxCount"] = $isMaxCountBounded ? $this->maxCount : null;
        $dictionary["lazyDestinationEntityName"] = $this->lazyDestinationEntityName;
        $dictionary["lazyInverseRelationshipName"] = $this->lazyInverseRelationshipName;
        return $dictionary;
    }
}
