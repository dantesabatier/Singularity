<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\Model\Entity;
use Override;
use Sabatier\Foundation\Set;

final class AuthorizableCodeGenerator extends EntityRoleCodeGenerator
{
    /** @var array<string, string> */
    private const array authorizableProperties = [
        "username" => "string",
        "password" => "?string",
        "isEnabled" => "bool",
        "refreshTokenVersion" => "int",
        "roles" => "Set",
    ];
    /** @var array<string, string> */
    #[Override]
    protected array $properties {
        get => self::authorizableProperties;
    }

    #[Override]
    protected function appliesTo(Entity $entity): bool
    {
        return $entity->isAuthorizable;
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
        $method .= "            \"isEnabled\" => AttributeType::boolean,\n";
        $method .= "            \"refreshTokenVersion\" => AttributeType::integer64,\n";
        $method .= "            \"roles\" => [\n";
        $method .= "                \"name\" => AttributeType::string,\n";
        $method .= "            ]\n";
        $method .= "        ]);\n";
        return "$method    }";
    }
}
