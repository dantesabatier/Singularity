<?php

namespace App\AI;

use App\Model\Entity;
use Override;
use Sabatier\Foundation\Dictionary;

final class ValidatePatchProcessor extends ModelPatchProcessor
{
    #[Override]
    protected function process(): void
    {
        /** @var Dictionary<true> $plannedEntities */
        $plannedEntities = new Dictionary();
        /** @var Dictionary<Dictionary<true>> $plannedAttributesByEntity */
        $plannedAttributesByEntity = new Dictionary();
        /** @var Dictionary<Dictionary<true>> $plannedRelationshipsByEntity */
        $plannedRelationshipsByEntity = new Dictionary();
        foreach ($this->patch->operations as $index => $operation) {
            if ($operation instanceof CreateEntityPatchOperation) {
                $this->validateCreateEntity($operation, $index, $plannedEntities);
            } elseif ($operation instanceof AddAttributePatchOperation) {
                $this->validateAddAttribute($operation, $index, $plannedEntities, $plannedAttributesByEntity);
            } elseif ($operation instanceof AddRelationshipPatchOperation) {
                $this->validateAddRelationship($operation, $index, $plannedEntities, $plannedRelationshipsByEntity);
            }
        }
    }

    /**
     * @param CreateEntityPatchOperation $operation
     * @param int $index
     * @param Dictionary<true> $plannedEntities
     */
    private function validateCreateEntity(CreateEntityPatchOperation $operation, int $index, Dictionary $plannedEntities): void
    {
        $name = $operation->name;
        if ($this->model->entitiesByName->offsetExists($name) || $plannedEntities->offsetExists($name)) {
            $this->addWarning("Operation at index $index duplicates entity `$name`.");
            return;
        }
        $plannedEntities[$name] = true;
    }

    /**
     * @param AddAttributePatchOperation $operation
     * @param int $index
     * @param Dictionary<true> $plannedEntities
     * @param Dictionary<Dictionary<true>> $plannedAttributesByEntity
     */
    private function validateAddAttribute(AddAttributePatchOperation $operation, int $index, Dictionary $plannedEntities, Dictionary $plannedAttributesByEntity): void
    {
        $entityName = $operation->entityName;
        $attributeName = $operation->name;
        if (!$this->entityExists($entityName, $plannedEntities)) {
            $this->addWarning("Operation at index $index references unknown entity `$entityName`.");
            return;
        }
        if ($this->attributeExists($entityName, $attributeName, $plannedAttributesByEntity)) {
            $this->addWarning("Operation at index $index duplicates attribute `$entityName.$attributeName`.");
            return;
        }
        $this->rememberName($plannedAttributesByEntity, $entityName, $attributeName);
    }

    /**
     * @param AddRelationshipPatchOperation $operation
     * @param int $index
     * @param Dictionary<true> $plannedEntities
     * @param Dictionary<Dictionary<true>> $plannedRelationshipsByEntity
     */
    private function validateAddRelationship(AddRelationshipPatchOperation $operation, int $index, Dictionary $plannedEntities, Dictionary $plannedRelationshipsByEntity): void
    {
        $entityName = $operation->entityName;
        $destinationEntityName = $operation->destinationEntityName;
        $relationshipName = $operation->name;
        if (!$this->entityExists($entityName, $plannedEntities)) {
            $this->addWarning("Operation at index $index references unknown source entity `$entityName`.");
            return;
        }
        if (!$this->entityExists($destinationEntityName, $plannedEntities)) {
            $this->addWarning("Operation at index $index references unknown destination entity `$destinationEntityName`.");
            return;
        }
        if ($this->relationshipExists($entityName, $relationshipName, $plannedRelationshipsByEntity)) {
            $this->addWarning("Operation at index $index duplicates relationship `$entityName.$relationshipName`.");
            return;
        }
        $this->rememberName($plannedRelationshipsByEntity, $entityName, $relationshipName);
    }

    /**
     * @param string $entityName
     * @param Dictionary<true> $plannedEntities
     * @return bool
     */
    private function entityExists(string $entityName, Dictionary $plannedEntities): bool
    {
        if ($this->model->entitiesByName->offsetExists($entityName)) {
            return true;
        }
        return $plannedEntities->offsetExists($entityName);
    }

    /**
     * @param string $entityName
     * @param string $attributeName
     * @param Dictionary<Dictionary<true>> $plannedAttributesByEntity
     * @return bool
     */
    private function attributeExists(string $entityName, string $attributeName, Dictionary $plannedAttributesByEntity): bool
    {
        /** @var Entity|null $entity */
        $entity = $this->model->entitiesByName[$entityName];
        if ($entity?->attributesByName->offsetExists($attributeName)) {
            return true;
        }
        return $plannedAttributesByEntity[$entityName]?->offsetExists($attributeName) ?? false;
    }

    /**
     * @param string $entityName
     * @param string $relationshipName
     * @param Dictionary<Dictionary<true>> $plannedRelationshipsByEntity
     * @return bool
     */
    private function relationshipExists(string $entityName, string $relationshipName, Dictionary $plannedRelationshipsByEntity): bool
    {
        /** @var Entity|null $entity */
        $entity = $this->model->entitiesByName[$entityName];
        if ($entity?->relationshipsByName->offsetExists($relationshipName)) {
            return true;
        }
        return $plannedRelationshipsByEntity[$entityName]?->offsetExists($relationshipName) ?? false;
    }

    /**
     * @param Dictionary<Dictionary<true>> $plannedNamesByEntity
     * @param string $entityName
     * @param string $name
     */
    private function rememberName(Dictionary $plannedNamesByEntity, string $entityName, string $name): void
    {
        $plannedNamesByEntity[$entityName] ??= new Dictionary();
        $plannedNamesByEntity[$entityName][$name] = true;
    }

    private function addWarning(string $message): void
    {
        $this->patch->addWarning(new PatchWarning($message));
    }
}
