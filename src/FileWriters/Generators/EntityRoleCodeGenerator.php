<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\PropertyBlock;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\Property;
use App\Model\Relationship;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

abstract class EntityRoleCodeGenerator
{
    /** @var array<string, string> */
    abstract protected array $properties {
        get;
    }
    /** @var array<string> */
    public array $reservedPropertyNames {
        get => array_keys($this->properties);
    }

    public function __construct(private readonly PropertyAttributeGenerator $accessControlGenerator)
    {
    }

    abstract protected function appliesTo(Entity $entity): bool;

    /**
     * @param Set<string> $existingClassProperties
     * @param Set<string> $uses
     * @return ArrayClass<PropertyBlock>
     */
    public function generatePropertyBlocks(Entity $entity, Set $existingClassProperties, Set $uses): ArrayClass
    {
        if (!$this->appliesTo($entity)) {
            return new ArrayClass();
        }
        /** @var Dictionary<string> $properties */
        $properties = new Dictionary($this->properties);
        if (!$existingClassProperties->isEmpty) {
            $properties = $properties->filter(fn(string $type, string $name) => !$existingClassProperties->contains(fn(string $existing) => str_contains($existing, "\$$name")));
        }
        if ($properties->isEmpty) {
            return new ArrayClass();
        }
        return $properties->map(fn(string $type, string $name): PropertyBlock => $this->generatePropertyBlock($entity, $name, $type, $uses));
    }

    /**
     * @param Set<string> $uses
     */
    private function generatePropertyBlock(Entity $entity, string $name, string $type, Set $uses): PropertyBlock
    {
        /** @var Property|null $property */
        $property = $entity->attributes->first(fn(Attribute $attribute) => $attribute->name === $name) ?? $entity->relationships->first(fn(Relationship $relationship) => $relationship->name === $name);
        /** @var Set<string> $phpAttributes */
        $phpAttributes = new Set();
        $phpAttributes->insert("#[Override]");
        if ($property && !$property->accessControls->isEmpty) {
            $phpAttributes->formUnion($this->accessControlGenerator->generateAttributes($property, $uses));
        }
        $isNullable = str_starts_with($type, "?");
        $cleanType = $isNullable ? substr($type, 1) : $type;
        return new PropertyBlock($name, $cleanType, $isNullable, $phpAttributes);
    }
}
