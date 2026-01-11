<?php

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
 * Generates @method doc-block annotations for relationship magic methods
 *
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
        $relationships = $entity->relationships->sorted([new SortDescriptor("position")]);
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
        $methods = [
            new GeneratedMethod("void add{$relationshipName}Object($entityClassName \$object)"),
            new GeneratedMethod("void remove{$relationshipName}Object($entityClassName \$object)"),
            new GeneratedMethod("void add$relationshipName($setClassName \$objects)"),
            new GeneratedMethod("void remove$relationshipName($setClassName \$objects)"),
            new GeneratedMethod("$setClassName<$entityClassName> intersect$relationshipName($setClassName \$objects)"),
            new GeneratedMethod("void set$relationshipName($setClassName \$objects)")
        ];
        return new ArrayClass($methods)->map(fn(GeneratedMethod $method) => $method->toDocBlock())->join("\n");
    }
}
