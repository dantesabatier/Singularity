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

/**
 * Generates code specific to Authorizable entities
 */
final readonly class AuthorizableCodeGenerator
{
    /** @var array<string, string> */
    private const array authorizableProperties = [
        "username" => "string",
        "password" => "?string",
        "refreshTokenVersion" => "int",
        "roles" => "Set",
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
        if (!$entity->isAuthorizable) {
            return new ArrayClass();
        }
        /** @var Dictionary<string> $authorizableProperties */
        $authorizableProperties = new Dictionary(self::authorizableProperties);
        if (!$existingClassProperties->isEmpty) {
            $authorizableProperties = $authorizableProperties->filter(fn(string $type, string $name) => !$existingClassProperties->contains(fn(string $existing) => str_contains($existing, "\$$name")));
        }
        if ($authorizableProperties->isEmpty) {
            return new ArrayClass();
        }
        return $authorizableProperties->map(fn(string $type, string $name): PropertyBlock => $this->generatePropertyBlock($entity, $name, $type, $uses));
    }

    /**
     * @param Set<string> $uses
     */
    private function generatePropertyBlock(Entity $entity, string $name, string $type, Set $uses): PropertyBlock
    {
        /** @var Property|null $property */
        $property = $entity->attributes->first(fn(Attribute $attribute) => $attribute->name === $name) ?? $entity->relationships->first(fn(Relationship $relationship) => $relationship->name === $name);
        $phpAttributes = new Set();
        if ($property && !$property->accessControls->isEmpty) {
            $phpAttributes = $this->accessControlGenerator->generateAttributes($property, $uses);
        }
        $isNullable = str_starts_with($type, "?");
        $cleanType = $isNullable ? substr($type, 1) : $type;
        return new PropertyBlock($name, $cleanType, $isNullable, $phpAttributes);
    }

    /**
     * @param Entity $entity
     * @param Set<string> $methods
     * @return string|null
     */
    public function generateDefaultRepresentationMethod(Entity $entity, Set $methods): ?string
    {
        if (!$entity->isAuthorizable) {
            return null;
        }
        if ($methods->contains(fn(string $method) => str_contains($method, "defaultRepresentation"))) {
            return null;
        }
        $method = "\n";
        $method .= "    #[Override]\n";
        $method .= "    public static function defaultRepresentation(): Dictionary\n";
        $method .= "    {\n";
        $method .= "        return Dictionary::dictionaryWithArray([\n";
        $method .= "            \"username\" => AttributeType::string,\n";
        $method .= "            \"password\" => AttributeType::string,\n";
        $method .= "            \"roles\" => [\n";
        $method .= "                \"name\" => AttributeType::string,\n";
        $method .= "            ]\n";
        $method .= "        ]);\n";
        return "$method    }";
    }

    /**
     * @return array<string>
     */
    public function getReservedPropertyNames(): array
    {
        return array_keys(self::authorizableProperties);
    }
}
