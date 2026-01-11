<?php

namespace App\FileWriters\Generators;

use App\Model\AccessControl;
use App\Model\Property;
use App\Model\Role;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;
use function Sabatier\Foundation\class_name;

/**
 * Generates PHP attributes for access control
 */
final readonly class AccessControlGenerator
{
    private string $scopeClass;

    public function __construct()
    {
        $this->scopeClass = class_name(AuthorizationScope::class);
    }

    /**
     * @param Set<string> $uses Reference to uses set to add necessary imports
     * @return Set<string>
     */
    public function generateAttributes(Property $property, Set $uses): Set
    {
        /** @var Set<AccessControl> $accessControls */
        $accessControls = $property->accessControls;
        if ($accessControls->isEmpty) {
            /** @var Set<string> */
            return new Set();
        }
        /** @var Set<string> */
        return $accessControls->map(function (AccessControl $accessControl) use ($uses): string {
            $uses->insert("use Sabatier\\Service\\$accessControl->name;");
            $uses->insert("use " . AuthorizationScope::class . ";");
            if ($accessControl->roles->isEmpty) {
                return "#[$accessControl->name]";
            }
            return "#[$accessControl->name({$accessControl->roles->map(fn(Role $role) => "\"$role->name\"")}, $this->scopeClass::{$accessControl->scope->name})]";
        });
    }

    public function hasAccessControls(Property $property): bool
    {
        return !$property->accessControls->isEmpty;
    }
}
