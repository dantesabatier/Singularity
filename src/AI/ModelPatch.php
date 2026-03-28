<?php

namespace App\AI;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;
use const App\UndefinedStringValue;

final class ModelPatch extends ObjectClass
{
    public string $summary = UndefinedStringValue {
        set {
            $this->summary = $value |> trim(...);
        }
    }
    /** @var ArrayClass<PatchOperation> */
    public ArrayClass $operations {
        get => $this->operations ??= new ArrayClass();
    }
    /** @var ArrayClass<PatchWarning> */
    public ArrayClass $warnings {
        get => $this->warnings ??= new ArrayClass();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["summary" => $this->summary, "operations" => $this->operations, "warnings" => $this->warnings];
    }
}
