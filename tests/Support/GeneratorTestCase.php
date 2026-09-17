<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Model\AccessControl;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\EntityType;
use App\Model\FetchedProperty;
use App\Model\Model;
use App\Model\Relationship;
use App\Model\Role;
use Exception;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;

/**
 * Builds the entities the code generators read, one property at a time.
 *
 * `attributes`, `relationships` and `fetchedProperties` are fetched properties, so they stay empty
 * until the context is saved; every test therefore seals its fixture with {@see self::seal()}
 * before handing the entity to a generator.
 *
 * The model makes a property optional by default, which would render every fixture nullable, so the
 * helpers below declare the required case and each test that wants nullability asks for it.
 */
abstract class GeneratorTestCase extends CoreDataTestCase
{
    protected Model $model;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = $this->makeProject()->model ?? self::fail("Project has no model");
    }

    /**
     * Returns an entity with no properties at all, which each test fills as it needs.
     * @throws Exception
     */
    protected function makeEmptyEntity(string $name): Entity
    {
        $entity = new Entity($this->context);
        $entity->name = $name;
        $this->model->addEntitiesObject($entity);
        return $entity;
    }

    /**
     * @throws Exception
     */
    protected function addAttribute(Entity $entity, string $name, AttributeType $type = AttributeType::string): Attribute
    {
        $attribute = $this->makeAttribute($name);
        $attribute->type = $type;
        $attribute->isOptional = false;
        $entity->addPropertiesObject($attribute);
        return $attribute;
    }

    /**
     * @throws Exception
     */
    protected function addRelationship(Entity $entity, string $name, string $destinationEntityName, bool $isToMany = false): Relationship
    {
        $relationship = new Relationship($this->context);
        $relationship->name = $name;
        $relationship->lazyDestinationEntityName = $destinationEntityName;
        $relationship->lazyInverseRelationshipName = "";
        $relationship->isToMany = $isToMany;
        $relationship->isOptional = false;
        $entity->addPropertiesObject($relationship);
        return $relationship;
    }

    /**
     * @throws Exception
     */
    protected function addFetchedProperty(Entity $entity, string $name, string $fetchRequestEntityName): FetchedProperty
    {
        $fetchedProperty = new FetchedProperty($this->context);
        $fetchedProperty->name = $name;
        $fetchedProperty->fetchRequestEntityName = $fetchRequestEntityName;
        $fetchedProperty->isOptional = false;
        $entity->addPropertiesObject($fetchedProperty);
        return $fetchedProperty;
    }

    /**
     * Grants a property one access control, naming the roles it admits.
     * @param array<string> $roleNames
     * @throws Exception
     */
    protected function addAccessControl(Attribute|Relationship|Entity $target, string $name, array $roleNames = ["Admin"], AuthorizationScope $scope = AuthorizationScope::all, ?string $predicateString = null): AccessControl
    {
        $accessControl = new AccessControl($this->context);
        $accessControl->name = $name;
        $accessControl->scope = $scope;
        $accessControl->isEnabled = true;
        $accessControl->predicateString = $predicateString;
        foreach ($roleNames as $roleName) {
            $role = new Role($this->context);
            $role->name = $roleName;
            $this->model->addRolesObject($role);
            $accessControl->addRolesObject($role);
        }
        $target->addAccessControlsObject($accessControl);
        return $accessControl;
    }

    /**
     * The derived `isAuthorizable` family is computed by the store while the object is still a
     * fault, which a freshly built fixture never is, so the flag is written rather than derived.
     * @throws Exception
     */
    protected function makeRoleEntity(string $name, EntityType $type): Entity
    {
        $entity = $this->makeEmptyEntity($name);
        $entity->type = $type;
        $entity->setValueForKey(true, match ($type) {
            EntityType::authorizable => "isAuthorizable",
            EntityType::authorizableRole => "isAuthorizableRole",
            EntityType::authorization => "isAuthorization",
            EntityType::none => self::fail("An entity with no role cannot be marked as one"),
        });
        return $entity;
    }

    /**
     * Commits the fixture so the entity's fetched property collections resolve.
     * @throws Exception
     */
    protected function seal(Entity $entity): Entity
    {
        $this->context->save();
        return $entity;
    }

    /**
     * @return Set<string>
     */
    protected function emptyStringSet(): Set
    {
        /** @var Set<string> $set */
        $set = new Set();
        return $set;
    }
}
