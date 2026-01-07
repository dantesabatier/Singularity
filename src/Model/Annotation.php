<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * @property string|null $name
 * @property bool|null $isEnabled
 * @property Property|null $property
 * @property Set<Argument> $arguments
 * @method void addArgumentsObject(Argument $object)
 * @method void removeArgumentsObject(Argument $object)
 * @method void addArguments(Set $objects)
 * @method void removeArguments(Set $objects)
 * @method Set<Argument> intersectArguments(Set $objects)
 * @method void setArguments(Set $objects)
 */
final class Annotation extends ManagedObject
{
}
