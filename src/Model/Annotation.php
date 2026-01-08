<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * @property string|null $name
 * @property bool|null $isEnabled
 * @property Property|null $property
 * @property Set<Scope> $scopes
 * @method void addScopesObject(Scope $object)
 * @method void removeScopesObject(Scope $object)
 * @method void addScopes(Set $objects)
 * @method void removeScopes(Set $objects)
 * @method Set<Scope> intersectScopes(Set $objects)
 * @method void setScopes(Set $objects)
 */
final class Annotation extends ManagedObject
{
}
