<?php

namespace App\AI;

use Override;

final class CreateEntityPatchOperation extends PatchOperation
{
    #[Override]
    protected PatchObjectBuilder $builder {
        get => $this->builder ??= new EntityBuilder($this, $this->runtimeContext->managedObjectContext, $this->runtimeContext->model);
    }

    public function __construct(PatchRuntimeContext $runtimeContext)
    {
        parent::__construct(PatchOperationType::createEntity, $runtimeContext);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["type" => $this->type, "name" => $this->name];
    }
}
