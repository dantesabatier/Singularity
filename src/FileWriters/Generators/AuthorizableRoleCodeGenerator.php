<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\Model\Entity;
use Override;

final class AuthorizableRoleCodeGenerator extends EntityRoleCodeGenerator
{
    /** @var array<string, string> */
    private const array authorizableRoleProperties = [
        "name" => "string",
        "authorizations" => "Set",
    ];
    /** @var array<string, string> */
    #[Override]
    protected array $properties {
        get => self::authorizableRoleProperties;
    }

    #[Override]
    protected function appliesTo(Entity $entity): bool
    {
        return $entity->isAuthorizableRole;
    }
}
