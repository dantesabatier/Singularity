<?php

namespace App\AI;

use App\Model\Model;
use Override;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\canonical;
use function Sabatier\Foundation\string_begins_with;
use function Sabatier\Foundation\string_matches;
use function Sabatier\Foundation\string_split_trimmed;

final class TranslatePatchProcessor extends ModelPatchProcessor
{
    public function __construct(ModelPatch $patch, Model $model, public string $prompt {
        set {
            $this->prompt = $value |> trim(...);
        }
    })
    {
        parent::__construct($patch, $model);
    }

    #[Override]
    protected function process(): void
    {
        if ($this->prompt === "") {
            $this->addWarning("Prompt is empty.");
            $this->patch->summary = "No changes proposed";
            return;
        }
        $prompt = canonical($this->prompt);
        $tokens = string_split_trimmed($prompt, " ");
        if (string_begins_with($prompt, "create entity") && count($tokens) >= 3) {
            $name = $tokens[2];
            if (!string_matches($name, "[a-zA-Z_][a-zA-Z0-9_]*", CompareOptions::quoted)) {
                $this->addWarning("Entity name is invalid.");
                $this->patch->summary = "No changes proposed";
                return;
            }
            $operation = new CreateEntityPatchOperation($this->model);
            $operation->name = $name;
            $this->patch->addOperation($operation);
            $this->patch->summary = "Create entity $name";
            return;
        }
        if (string_begins_with($prompt, "add attribute") && count($tokens) >= 5 && $tokens[3] === "to") {
            $attributeName = $tokens[2];
            $entityName = $tokens[4];
            if (!string_matches($attributeName, "[a-zA-Z_][a-zA-Z0-9_]*", CompareOptions::quoted)) {
                $this->addWarning("Attribute name is invalid.");
                $this->patch->summary = "No changes proposed";
                return;
            }
            if (!string_matches($entityName, "[a-zA-Z_][a-zA-Z0-9_]*", CompareOptions::quoted)) {
                $this->addWarning("Entity name is invalid.");
                $this->patch->summary = "No changes proposed";
                return;
            }
            $operation = new AddAttributePatchOperation($this->model);
            $operation->name = $attributeName;
            $operation->entityName = $entityName;
            $this->patch->addOperation($operation);
            $this->patch->summary = "Add attribute $attributeName to $entityName";
            return;
        }
        $this->addWarning("Prompt not recognized.");
        $this->patch->summary = "No changes proposed";
    }

    private function addWarning(string $message): void
    {
        $this->patch->addWarning(new PatchWarning($message));
    }
}
