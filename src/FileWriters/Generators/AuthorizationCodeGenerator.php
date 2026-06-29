<?php

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\PropertyBlock;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\Property;
use App\Model\Relationship;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

final readonly class AuthorizationCodeGenerator
{
    /** @var array<string, string> */
    private const array authorizationProperties = [
        "name" => "string",
        "type" => "AuthorizationType",
        "scope" => "AuthorizationScope",
    ];

    public function __construct(private PropertyAttributeGenerator $accessControlGenerator)
    {
    }

    /**
     * @param Set<string> $existingClassProperties
     * @param Set<string> $uses
     * @return ArrayClass<PropertyBlock>
     */
    public function generatePropertyBlocks(Entity $entity, Set $existingClassProperties, Set $uses): ArrayClass
    {
        if (!$entity->isAuthorization) {
            return new ArrayClass();
        }
        /** @var Dictionary<string> $authorizationProperties */
        $authorizationProperties = new Dictionary(self::authorizationProperties);
        if (!$existingClassProperties->isEmpty) {
            $authorizationProperties = $authorizationProperties->filter(fn(string $type, string $name) => !$existingClassProperties->contains(fn(string $existing) => str_contains($existing, "\$$name")));
        }
        if ($authorizationProperties->isEmpty) {
            return new ArrayClass();
        }
        return $authorizationProperties->map(fn(string $type, string $name): PropertyBlock => $this->generatePropertyBlock($entity, $name, $type, $uses));
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

    /**
     * @return array<string>
     */
    public function getReservedPropertyNames(): array
    {
        return array_keys(self::authorizationProperties);
    }
}
