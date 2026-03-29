<?php

namespace App\AI;

use App\Model\Model;
use Override;
use Sabatier\CoreData\AttributeType;

final class AddAttributePatchOperation extends AddPropertyPatchOperation
{
    #[Override]
    protected PatchObjectBuilder $builder {
        get => $this->builder ??= new AttributeBuilder($this);
    }
    public AttributeType $attributeType = AttributeType::undefined {
        set(AttributeType|int $value) {
            if (is_int($value)) {
                $value = AttributeType::from($value);
            }
            $this->attributeType = $value;
        }
    }

    public function __construct(Model $model)
    {
        parent::__construct(PatchOperationType::addAttribute, $model);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["name" => $this->name, "type" => $this->type, "entityName" => $this->entityName, "attributeType" => $this->attributeType, "isOptional" => $this->isOptional];
    }
}
