<?php

namespace App\AI;

use Override;
use Sabatier\CoreData\AttributeType;

final class AddAttributePatchOperation extends AddPropertyPatchOperation
{
    #[Override]
    protected PatchObjectBuilder $builder {
        get => $this->builder ??= new AttributeBuilder($this, $this->runtimeContext->managedObjectContext, $this->runtimeContext->model);
    }
    public AttributeType $attributeType = AttributeType::undefined {
        set(AttributeType|int $value) {
            if (is_int($value)) {
                $value = AttributeType::from($value);
            }
            $this->attributeType = $value;
        }
    }

    public function __construct(PatchRuntimeContext $runtimeContext)
    {
        parent::__construct(PatchOperationType::addAttribute, $runtimeContext);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["name" => $this->name, "type" => $this->type, "entityName" => $this->entityName, "attributeType" => $this->attributeType, "isOptional" => $this->isOptional];
    }
}
