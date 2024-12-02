<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\Model;

use Override;
use Sabatier\CoreData\DeleteRule;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyValueObservedChange;
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
    private(set) ?Entity $destinationEntity {
        get => $this->destinationEntity ??= $this->entityProperty?->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $this->lazyDestinationEntityName);
    }
    private(set) ?Relationship $inverseRelationship {
        get => $this->inverseRelationship ??= $this->destinationEntity?->relationships?->first(fn(Relationship $relationship): bool => $relationship->name === $this->lazyInverseRelationshipName);
    }
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = parent::$dictionaryRepresentation::get();
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

    public function validateDeleteRule(DeleteRule|int &$deleteRule): bool
    {
        if (is_int($deleteRule)) {
            $deleteRule = DeleteRule::from($deleteRule);
        }
        return true;
    }
}
