<?php

namespace App\AI;

use App\Model\Model;
use InvalidArgumentException;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

final class PatchOperationFactory
{
    private PatchOperationType $operationType {
        get {
            if (isset($this->operationType)) {
                return $this->operationType;
            }
            /** @var string $typeValue */
            $typeValue = $this->rawOperation["type"] ?? PatchOperationType::undefined->value;
            return $this->operationType = PatchOperationType::tryFrom($typeValue) ?? throw new InvalidArgumentException(sprintf("Unsupported patch operation type (%s)%s.", typeof($typeValue), human_readable_value($typeValue)));
        }
    }
    private(set) PatchOperation $operation {
        get {
            if (isset($this->operation)) {
                return $this->operation;
            }
            $operationClass = match ($this->operationType) {
                PatchOperationType::createEntity => CreateEntityPatchOperation::class,
                PatchOperationType::addAttribute => AddAttributePatchOperation::class,
                PatchOperationType::addRelationship => AddRelationshipPatchOperation::class,
                PatchOperationType::undefined => throw new InvalidArgumentException("Patch operation type \"undefined\" is not supported."),
            };
            $operation = new $operationClass($this->model);
            $operation->setValuesForKeys($this->rawOperation);
            return $this->operation = $operation;
        }
    }

    /**
     * @param Dictionary<mixed> $rawOperation
     */
    public function __construct(private readonly Dictionary $rawOperation, private readonly Model $model)
    {
    }
}
