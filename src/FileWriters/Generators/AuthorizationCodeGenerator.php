<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\Model\Entity;
use Override;

final class AuthorizationCodeGenerator extends EntityRoleCodeGenerator
{
    /** @var array<string, string> */
    private const array authorizationProperties = [
        "name" => "string",
        "type" => "AuthorizationType",
        "scope" => "AuthorizationScope",
    ];
    /** @var array<string, string> */
    #[Override]
    protected array $properties {
        get => self::authorizationProperties;
    }

    #[Override]
    protected function appliesTo(Entity $entity): bool
    {
        return $entity->isAuthorization;
    }
}
