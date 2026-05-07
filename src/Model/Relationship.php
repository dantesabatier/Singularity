<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\DeleteRule;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\PropertyDescription;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;

/**
 * @property string $lazyDestinationEntityName
 * @property string $lazyInverseRelationshipName
 * @property bool $isToMany
 * @property bool $isOrdered
 * @property DeleteRule $deleteRule
 * @property int<0, max>|null $minCount
 * @property int<0, max>|null $maxCount
 * @property bool $isMinCountBounded
 * @property bool $isMaxCountBounded
 * @property bool $isOwner
 */
final class Relationship extends Property
{
    private(set) ?Entity $destinationEntity {
        get => $this->destinationEntity ??= $this->entityProperty?->model?->entities?->first(fn(Entity $entity): bool => $entity->name === $this->lazyDestinationEntityName);
    }
    private(set) ?Relationship $inverseRelationship {
        get => $this->inverseRelationship ??= $this->destinationEntity?->relationships?->first(fn(Relationship $relationship): bool => $relationship->name === $this->lazyInverseRelationshipName);
    }
    /** @var list<string> */
    private const array relationshipDescriptionKeys = ["name", "isOptional", "isTransient", "renamingIdentifier", "versionHashModifier", "lazyDestinationEntityName", "lazyInverseRelationshipName", "isToMany", "isOrdered", "deleteRule", "minCount", "maxCount", "isSensitive"];
    /** @var ArrayClass<string> */
    private(set) ArrayClass $relationshipDescriptionKeys {
        get => $this->relationshipDescriptionKeys ??= new ArrayClass(self::relationshipDescriptionKeys);
    }
    private(set) RelationshipDescription $relationshipDescription {
        get {
            if (isset($this->relationshipDescription)) {
                return $this->relationshipDescription;
            }
            $relationshipDescription = new RelationshipDescription();
            $relationshipDescription->setValuesForKeys($this->dictionaryWithValues($this->relationshipDescriptionKeys));
            return $this->relationshipDescription = $relationshipDescription;
        }
    }
    #[Override]
    public PropertyDescription $propertyDescription {
        get => $this->relationshipDescription;
    }

    public function __construct(ManagedObjectContext $managedObjectContext, ?EntityDescription $entity = null)
    {
        parent::__construct($managedObjectContext, $entity);
        $this->observe("isToMany", KeyValueObservingOptions::new, function (/** @noinspection PhpUnusedParameterInspection */ Relationship $relationship, KeyValueObservedChange $change): void {
            $relationship->minCount = null;
            $relationship->maxCount = null;
            $relationship->isOrdered = false;
            $relationship->isMinCountBounded = false;
            $relationship->isMaxCountBounded = false;
        });
        $this->observe("isMinCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship, KeyValueObservedChange $change): void {
            $relationship->minCount = $change->newValue ? $this->minCount : null;
        });
        $this->observe("isMaxCountBounded", KeyValueObservingOptions::new, function (Relationship $relationship, KeyValueObservedChange $change): void {
            $relationship->maxCount = $change->newValue ? $this->maxCount : null;
        });
    }

    public function validateDeleteRule(DeleteRule|int|null &$deleteRule): bool
    {
        if (is_int($deleteRule)) {
            $deleteRule = DeleteRule::from($deleteRule);
        }
        return true;
    }
}
