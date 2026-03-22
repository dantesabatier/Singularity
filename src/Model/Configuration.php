<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;

/**
 * @property string $name
 * @property Model|null $model
 */
final class Configuration extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
