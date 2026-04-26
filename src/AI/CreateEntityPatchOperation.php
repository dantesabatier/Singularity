<?php

declare(strict_types=1);

namespace App\AI;

use App\Model\Model;
use Override;

final class CreateEntityPatchOperation extends PatchOperation
{
    #[Override]
    protected PatchObjectBuilder $builder {
        get => $this->builder ??= new EntityBuilder($this);
    }

    public function __construct(Model $model)
    {
        parent::__construct(PatchOperationType::createEntity, $model);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["type" => $this->type, "name" => $this->name];
    }
}
