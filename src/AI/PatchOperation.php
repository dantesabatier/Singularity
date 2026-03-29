<?php

namespace App\AI;

use App\Model\Model;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ObjectClass;
use const App\UndefinedStringValue;

abstract class PatchOperation extends ObjectClass
{
    public string $name = UndefinedStringValue {
        set {
            $this->name = $value |> trim(...);
        }
    }
    abstract protected PatchObjectBuilder $builder {
        get;
    }
    public ManagedObject $object {
        get => $this->builder->object;
    }

    public function __construct(public PatchOperationType $type {
        set(PatchOperationType|string $value) {
            if (is_string($value)) {
                $value = PatchOperationType::from($value);
            }
            $this->type = $value;
        }
    }, public readonly Model $model)
    {
    }
}
