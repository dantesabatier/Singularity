<?php

namespace App\Model;

use Sabatier\CoreData\ManagedObject;

/**
 * @property string|null $name
 * @property int<0, max> $index
 * @property AccessControl|null $accessControl
 */
final class Role extends ManagedObject
{
}
