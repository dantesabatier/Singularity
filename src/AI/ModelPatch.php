<?php

namespace App\AI;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;

final class ModelPatch extends ObjectClass
{
    public string $summary = "";
    /** @var ArrayClass<PatchOperation> */
    private(set) ArrayClass $operations {
        get => $this->operations ??= new ArrayClass();
    }
    /** @var ArrayClass<PatchWarning> */
    private(set) ArrayClass $warnings {
        get => $this->warnings ??= new ArrayClass();
    }
    /** @var ArrayClass<PatchPreviewItem> */
    public ArrayClass $items {
        get => $this->operations->map(function (PatchOperation $operation): PatchPreviewItem {
            if ($operation instanceof CreateEntityPatchOperation) {
                return new PatchPreviewItem("Create Entity", "Entity `$operation->name` will be created.", code: "createEntity", meta: new Dictionary(["name" => $operation->name]));
            }
            if ($operation instanceof AddAttributePatchOperation) {
                return new PatchPreviewItem("Add Attribute", "Attribute `$operation->name` will be added to `$operation->entityName`.", code: "addAttribute", meta: new Dictionary(["entityName" => $operation->entityName, "name" => $operation->name, "attributeType" => $operation->attributeType->value, "isOptional" => $operation->isOptional]));
            }
            if ($operation instanceof AddRelationshipPatchOperation) {
                return new PatchPreviewItem("Add Relationship", "Relationship `$operation->name` will connect `$operation->entityName` to `$operation->destinationEntityName`.", code: "addRelationship", meta: new Dictionary(["entityName" => $operation->entityName, "name" => $operation->name, "destinationEntityName" => $operation->destinationEntityName, "inverseRelationshipName" => $operation->inverseRelationshipName, "isToMany" => $operation->isToMany, "isOptional" => $operation->isOptional]));
            }
            return new PatchPreviewItem("Patch Operation", "Operation `{$operation->type->value}` will be applied.", code: "patchOperation", meta: new Dictionary(["type" => $operation->type->value, "name" => $operation->name]));
        });
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
        return ["summary" => $this->summary, "operations" => $this->operations, "warnings" => $this->warnings, "items" => $this->items];
    }
}
