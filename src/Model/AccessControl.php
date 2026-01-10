<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * @property string|null $name
 * @property bool|null $isEnabled
 * @property Property|null $property
 * @property Set<Role> $roles
 * @method void addRolesObject(Role $object)
 * @method void removeRolesObject(Role $object)
 * @method void addRoles(Set $objects)
 * @method void removeRoles(Set $objects)
 * @method Set<Role> intersectRoles(Set $objects)
 * @method void setRoles(Set $objects)
 */
final class AccessControl extends ManagedObject
{
}
