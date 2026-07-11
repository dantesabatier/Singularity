<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\FileWriters\ValueObjects\GeneratedMethod;
use App\Model\Entity;
use App\Model\Relationship;
use Closure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use function Sabatier\Foundation\class_name;

/**
 * @psalm-type SubclassNameGenerator = Closure(Entity, string): string
 */
final readonly class MagicMethodDocGenerator
{
    /** @var SubclassNameGenerator */
    private Closure $classNameGenerator;

    /**
     * @param SubclassNameGenerator $classNameGenerator
     */
    public function __construct(Closure $classNameGenerator)
    {
        $this->classNameGenerator = $classNameGenerator;
    }

    /**
     * @return ArrayClass<string>
     */
    public function generate(Entity $entity, string $namespace): ArrayClass
    {
        $relationships = $entity->relationships->sorted([new SortDescriptor("position", false)]);
        $className = class_name(Set::class);
        /** @var ArrayClass<string> */
        return $relationships->compactMap(fn(Relationship $relationship) => $this->generateMethodsForRelationship($relationship, $namespace, $className));
    }

    private function generateMethodsForRelationship(Relationship $relationship, string $namespace, string $setClassName): ?string
    {
        if (!$relationship->isToMany) {
            return null;
        }
        if (!($destinationEntity = $relationship->destinationEntity)) {
            return null;
        }
        $relationshipName = ucfirst($relationship->name);
        $entityClassName = ($this->classNameGenerator)($destinationEntity, $namespace);
        return new ArrayClass([
            new GeneratedMethod("void add{$relationshipName}Object($entityClassName \$object)"),
            new GeneratedMethod("void remove{$relationshipName}Object($entityClassName \$object)"),
            new GeneratedMethod("void add$relationshipName($setClassName<$entityClassName> \$objects)"),
            new GeneratedMethod("void remove$relationshipName($setClassName<$entityClassName> \$objects)"),
            new GeneratedMethod("$setClassName<$entityClassName> intersect$relationshipName($setClassName<$entityClassName> \$objects)"),
            new GeneratedMethod("void set$relationshipName($setClassName<$entityClassName> \$objects)")
        ])->join("\n");
    }
}
