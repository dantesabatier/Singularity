<?php

namespace App\AI;

use const App\UndefinedStringValue;

abstract class AddPropertyPatchOperation extends PatchOperation
{
    public bool $isOptional = true;
    public string $entityName = UndefinedStringValue {
        set {
            $this->entityName = $value |> trim(...);
        }
    }
}
