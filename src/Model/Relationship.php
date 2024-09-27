<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Override;
use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Value;

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
    }

    public function __get(string $name)
    {
        if ($name == "destinationEntity") {
            $this->$name = $this->entityProperty->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $this->lazyDestinationEntityName);
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

    #[Override]
    public function awakeFromFetch(): void
    {
        /** @psalm-suppress UndefinedVariable */
        $observation = $this->observe("isToMany", KeyValueObservingOptions::new, function (/** @noinspection PhpUnusedParameterInspection */ Relationship $relationship, KeyValueObservedChange $change) use (&$observation): void {
            if ($relationship->isSuppressingKVO || $relationship->isSuppressingChangeNotifications) {
                return;
            }
            $observation->invalidate();
            $relationship->minCount = null;
            $relationship->maxCount = null;
            $relationship->isOrdered = false;
            $relationship->isMinCountBounded = false;
            $relationship->isMaxCountBounded = false;
        });
        $observation = $this->observe("isMinCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship, KeyValueObservedChange $change) use (&$observation): void {
            if ($relationship->isSuppressingKVO || $relationship->isSuppressingChangeNotifications) {
                return;
            }
            $observation->invalidate();
            $relationship->minCount = $change->newValue ? $this->minCount : null;
        });
        $observation = $this->observe("isMaxCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship, KeyValueObservedChange $change) use (&$observation): void {
            if ($relationship->isSuppressingKVO || $relationship->isSuppressingChangeNotifications) {
                return;
            }
            $observation->invalidate();
            $relationship->maxCount = $change->newValue ? $this->maxCount : null;
        });
    }

    public function validateDeleteRule(DeleteRule|Number|Nil|int|null &$deleteRule): bool
    {
        if ($deleteRule instanceof Value) {
            $deleteRule = $deleteRule->value;
        }
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
            if ($isOrdered = $this->isOrdered) {
                $dictionary["isOrdered"] = $isOrdered;
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
        }
        $deleteRule = $this->deleteRule;
        if ($deleteRule !== DeleteRule::nullifyDeleteRule) {
            $dictionary["deleteRule"] = $deleteRule;
        }
        $dictionary["lazyDestinationEntityName"] = $this->lazyDestinationEntityName;
        $dictionary["lazyInverseRelationshipName"] = $this->lazyInverseRelationshipName;
        return $dictionary;
    }
}
