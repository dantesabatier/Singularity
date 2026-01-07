<?php

namespace App\Model;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;

/**
 * @property string|null $name
 * @property mixed $value
 * @property AttributeType $type
 * @property Annotation|null $annotation
 */
final class Argument extends ManagedObject
{
    public function validateType(AttributeType|int|null &$type): bool
    {
        if (is_int($type)) {
            $type = AttributeType::from($type);
        }
        return true;
    }
}
