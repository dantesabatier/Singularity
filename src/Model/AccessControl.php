<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizationScope;
use Throwable;
use const Sabatier\CoreData\ManagedObjectValidationError;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/**
 * @property string $name
 * @property AuthorizationScope $scope
 * @property bool $isEnabled
 * @property string|null $predicateString
 * @property Property|null $property
 * @property Set<Role> $roles
 * @method void addRolesObject(Role $object)
 * @method void removeRolesObject(Role $object)
 * @method void addRoles(Set<Role> $objects)
 * @method void removeRoles(Set<Role> $objects)
 * @method Set<Role> intersectRoles(Set<Role> $objects)
 * @method void setRoles(Set<Role> $objects)
 */
final class AccessControl extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        if ($this->predicateString !== null) {
            $predicateString = $this->predicateString |> trim(...);
            $this->predicateString = $predicateString === "" ? null : $predicateString;
        }
        if ($predicateString = $this->predicateString) {
            try {
                Predicate::format($predicateString);
            } catch (Throwable) {
                throw new InternalInconsistencyException(error: new Error(CocoaErrorDomain, ManagedObjectValidationError, new Dictionary([LocalizedDescriptionKey => "#$this->name condition must be a valid predicate", LocalizedFailureReasonErrorKey => sprintf("Unable to parse the condition \"%s\". Check the predicate syntax.", $predicateString)])));
            }
        }
    }

    public function validateScope(AuthorizationScope|int|null &$scope): bool
    {
        if (is_int($scope)) {
            $scope = AuthorizationScope::from($scope);
        }
        return true;
    }
}
