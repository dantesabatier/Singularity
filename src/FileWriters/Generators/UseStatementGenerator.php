<?php

namespace App\FileWriters\Generators;

use App\Model\Attribute;
use App\Model\Entity;
use App\Model\Relationship;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\Authorization;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationType;

final class UseStatementGenerator
{
    /**
     * @param Set<string> $existingUses
     * @return Set<string>
     */
    public function generate(Entity $entity, Set $existingUses): Set
    {
        /** @var Set<string> $uses */
        $uses = new Set($existingUses);
        /** @var Set<Attribute> $attributes */
        $attributes = new Set($entity->attributes);
        /** @var Set<string> $attributeUses */
        $attributeUses = $attributes->compactMap(function (Attribute $attribute): ?string {
            $attributeValueClassName = $this->getAttributeClassName($attribute);
            if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
                return "use $attributeValueClassName;";
            }
            return null;
        });
        $uses->formUnion($attributeUses);
        $relationships = $entity->relationships;
        if ($relationships->contains(fn(Relationship $relationship): bool => $relationship->isToMany)) {
            $uses->insert("use " . Set::class . ";");
        }
        $fetchedProperties = $entity->fetchedProperties;
        if (!$fetchedProperties->isEmpty) {
            $uses->insert("use " . ArrayClass::class . ";");
        }
        if (!$entity->superentity) {
            $uses->insert("use " . ManagedObject::class . ";");
        }
        if ($entity->isAuthorizable) {
            $uses->insert("use " . Authorizable::class . ";");
            $uses->insert("use " . AttributeType::class . ";");
            $uses->insert("use " . Dictionary::class . ";");
            $uses->insert("use " . Set::class . ";");
            $uses->insert("use Override;");
        }
        if ($entity->isAuthorizableRole) {
            $uses->insert("use " . AuthorizableRole::class . ";");
            $uses->insert("use " . Authorization::class . ";");
            $uses->insert("use " . Set::class . ";");
            $uses->insert("use Override;");
        }
        if ($entity->isAuthorization) {
            $uses->insert("use " . Authorization::class . ";");
            $uses->insert("use " . AuthorizationType::class . ";");
            $uses->insert("use " . AuthorizationScope::class . ";");
            $uses->insert("use Override;");
        }
        return $uses->sort();
    }

    private function getAttributeClassName(Attribute $attribute): ?string
    {
        return match ($attribute->type) {
            AttributeType::date => Date::class,
            AttributeType::uuid => UUID::class,
            AttributeType::uri => URL::class,
            AttributeType::objectID => ManagedObjectID::class,
            AttributeType::compositeAttributeType => Dictionary::class,
            default => $attribute->attributeValueClassName
        };
    }
}
