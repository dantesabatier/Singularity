<?php

namespace App\FileWriters\Generators;

use App\Model\AccessControl;
use App\Model\Property;
use App\Model\Relationship;
use App\Model\Role;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;
use function Sabatier\Foundation\class_name;

/**
 * Generates PHP attributes for access control
 */
final readonly class PropertyAttributeGenerator
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
        /** @var Set<string> $attributes */
        $attributes = new Set();
        if ($property instanceof Relationship && $property->isOwner) {
            $attributes->insert("#[Owner]");
        }
        $attributes->formUnion($property->accessControls->map(function (AccessControl $accessControl) use ($uses): string {
            $uses->insert("use Sabatier\\Service\\$accessControl->name;");
            $uses->insert("use " . AuthorizationScope::class . ";");
            return "#[$accessControl->name({$accessControl->roles->map(fn(Role $role) => "\"$role->name\"")}, $this->scopeClass::{$accessControl->scope->name})]";
        }));
        return $attributes;
    }

    public function shouldGenerateAttributes(Property $property): bool
    {
        return ($property instanceof Relationship && $property->isOwner) || !$property->accessControls->isEmpty;
    }
}
