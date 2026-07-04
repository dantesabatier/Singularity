<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;

/**
 * @property string $name
 * @property int<0, max> $index
 * @property AccessControl|null $accessControl
 */
final class Role extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
    }
}
