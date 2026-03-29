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
    private(set) ArrayClass $operations {
        get => $this->operations ??= new ArrayClass();
    }
    /** @var ArrayClass<PatchWarning> */
    private(set) ArrayClass $warnings {
        get => $this->warnings ??= new ArrayClass();
    }

    public function addOperation(PatchOperation $operation): void
    {
        $this->operations->append($operation);
    }

    public function addWarning(PatchWarning $warning): void
    {
        $this->warnings->append($warning);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["summary" => $this->summary, "operations" => $this->operations, "warnings" => $this->warnings];
    }
}
