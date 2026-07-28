<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property int<0, max> $index
 * @property Model|null $model
 * @property Set<AccessControl> $accessControls
 * @method void addAccessControlsObject(AccessControl $object)
 * @method void removeAccessControlsObject(AccessControl $object)
 * @method void addAccessControls(Set<AccessControl> $objects)
 * @method void removeAccessControls(Set<AccessControl> $objects)
 * @method Set<AccessControl> intersectAccessControls(Set<AccessControl> $objects)
 * @method void setAccessControls(Set<AccessControl> $objects)
 */
final class Role extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
