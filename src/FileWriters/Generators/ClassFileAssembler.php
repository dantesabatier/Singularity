<?php

declare(strict_types=1);

namespace App\FileWriters\Generators;

use App\Model\Entity;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\class_name;

final class ClassFileAssembler
{
    /**
     * @param Set<string> $uses
     * @param Set<string> $properties
     * @param ArrayClass<string> $methods
     */
    public function assemble(string $namespace, Entity $entity, Set $uses, Set $properties, ArrayClass $methods, string $declaration): string
    {
        $content = "<?php\n\n";
        $content .= "namespace $namespace;\n";
        $superentity = $entity->superentity;
        if (!$superentity) {
            $content .= "\n";
        }
        if (!$uses->isEmpty) {
            if ($superentity) {
                $content .= "\n";
            }
            $content .= $uses->sort()->join("\n");
        }
        $content .= "\n";
        if (!$properties->isEmpty || !$methods->isEmpty) {
            $content .= "\n/**\n";
            if (!$properties->isEmpty) {
                $content .= $properties->join("\n");
            }
            if (!$methods->isEmpty) {
                if (!$properties->isEmpty) {
                    $content .= "\n";
                }
                $content .= $methods->join("\n");
            }
            $content .= "\n";
            $content .= " */\n";
        }
        if ($entity->isAbstract) {
            $content .= "abstract ";
        } elseif ($entity->isFinal) {
            $content .= "final ";
        }
        return "$content$declaration";
    }

    public function createDefaultDeclaration(string $class, Entity $entity): string
    {
        $superclass = $entity->superentity?->name ?? class_name(ManagedObject::class);
        $implements = $entity->isAuthorizable ? " implements Authorizable" : "";
        return "class $class extends $superclass$implements\n{\n}\n";
    }
}
