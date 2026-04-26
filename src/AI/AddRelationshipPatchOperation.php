<?php

declare(strict_types=1);

namespace App\AI;

use App\Model\Model;
use Override;
use const App\UndefinedStringValue;

final class AddRelationshipPatchOperation extends AddPropertyPatchOperation
{
    #[Override]
    protected PatchObjectBuilder $builder {
        get => $this->builder ??= new RelationshipBuilder($this);
    }
    public string $destinationEntityName = UndefinedStringValue {
        set {
            $this->destinationEntityName = $value |> trim(...);
        }
    }
    public string $inverseRelationshipName = UndefinedStringValue;
    public bool $isToMany = false;

    public function __construct(Model $model)
    {
        parent::__construct(PatchOperationType::addRelationship, $model);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["name" => $this->name, "type" => $this->type, "entityName" => $this->entityName, "destinationEntityName" => $this->destinationEntityName, "isToMany" => $this->isToMany, "inverseRelationshipName" => $this->inverseRelationshipName, "isOptional" => $this->isOptional];
    }
}
