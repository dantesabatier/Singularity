<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;

/**
 * @property string $stringValue
 * @property Entity|null $entityProperty
 */
final class UniquenessConstraint extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->stringValue = $this->stringValue |> trim(...);
    }
}
