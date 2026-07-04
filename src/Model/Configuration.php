<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * @property string $name
 * @property Model|null $model
 * @property Set<Entity> $entities
 * @method void addEntitiesObject(Entity $object)
 * @method void removeEntitiesObject(Entity $object)
 * @method void addEntities(Set<Entity> $objects)
 * @method void removeEntities(Set<Entity> $objects)
 * @method Set<Entity> intersectEntities(Set<Entity> $objects)
 * @method void setEntities(Set<Entity> $objects)
 */
final class Configuration extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
