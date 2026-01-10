<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
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
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get {
            /** @var Dictionary<mixed> $dictionary */
            $dictionary = new Dictionary();
            $dictionary["name"] = $this->name;
            if ($isEnabled = $this->isEnabled) {
                $dictionary["isEnabled"] = $isEnabled;
            }
            $dictionary["roles"] = $this->roles->map(fn(Role $role): Dictionary => $role->dictionaryRepresentation);
            return $dictionary;
        }
    }
}
