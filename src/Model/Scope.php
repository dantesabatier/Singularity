<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;

/**
 * @property string|null $name
 * @property Annotation|null $annotation
 */
final class Scope extends ManagedObject
{
    public function validateType(AttributeType|int|null &$type): bool
    {
        if (is_int($type)) {
            $type = AttributeType::from($type);
        }
        return true;
    }
}
