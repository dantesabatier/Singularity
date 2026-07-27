<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\PropertyDescription;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property bool $isOptional
 * @property bool $isTransient
 * @property bool $isSensitive
 * @property string|null $renamingIdentifier
 * @property string|null $versionHashModifier
 * @property mixed $minValue
 * @property mixed $maxValue
 * @property bool $isMinValueBounded
 * @property bool $isMaxValueBounded
 * @property string|null $regex
 * @property int<0, max> $position
 * @property Entity|null $entityProperty
 * @property Set<AccessControl> $accessControls
 * @method void addAccessControlsObject(AccessControl $object)
 * @method void removeAccessControlsObject(AccessControl $object)
 * @method void addAccessControls(Set<AccessControl> $objects)
 * @method void removeAccessControls(Set<AccessControl> $objects)
 * @method Set<AccessControl> intersectAccessControls(Set<AccessControl> $objects)
 * @method void setAccessControls(Set<AccessControl> $objects)
 */
abstract class Property extends ManagedObject
{
    abstract public PropertyDescription $propertyDescription {
        get;
    }

    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->regex) {
            $this->regex = $this->regex |> trim(...);
        }
        if (!$this->isInserted && ($entity = $this->entityProperty)) {
            $all = $entity->properties->map(fn(Property $property): Property => $property);
            /** @var int<0, max> $position */
            $position = $all->indexOf($this) ?? $all->count;
            $this->position = $position;
        }
    }
}
