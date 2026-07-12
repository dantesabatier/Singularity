<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;

/**
 * @property string $name
 * @property AuthorizationScope $scope
 * @property bool $isEnabled
 * @property string|null $predicateString
 * @property Property|null $property
 * @property Set<Role> $roles
 * @method void addRolesObject(Role $object)
 * @method void removeRolesObject(Role $object)
 * @method void addRoles(Set<Role> $objects)
 * @method void removeRoles(Set<Role> $objects)
 * @method Set<Role> intersectRoles(Set<Role> $objects)
 * @method void setRoles(Set<Role> $objects)
 */
final class AccessControl extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->predicateString) {
            $this->predicateString = $this->predicateString |> trim(...);
        }
    }

    public function validateScope(AuthorizationScope|int|null &$scope): bool
    {
        if (is_int($scope)) {
            $scope = AuthorizationScope::from($scope);
        }
        return true;
    }
}
