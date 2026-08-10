<?php

declare(strict_types=1);

namespace App\FileWriters;

use App\FileWriters\Generators\AuthorizableCodeGenerator;
use App\FileWriters\Generators\AuthorizableRoleCodeGenerator;
use App\FileWriters\Generators\AuthorizationCodeGenerator;
use App\FileWriters\Generators\ClassDeclarationInjector;
use App\FileWriters\Generators\ClassFileAssembler;
use App\FileWriters\Generators\MagicMethodDocGenerator;
use App\FileWriters\Generators\PropertyAttributeGenerator;
use App\FileWriters\Generators\PropertyBlockGenerator;
use App\FileWriters\Generators\PropertyDocBlockGenerator;
use App\FileWriters\Generators\UseStatementGenerator;
use App\FileWriters\Parsers\ExistingClassParser;
use App\Model\Entity;
use Closure;
use Exception;
use Override;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;

/**
 * Generates PHP class files for Core Data entity subclasses
 *
 * @psalm-type SubclassNameGenerator = Closure(Entity, string): string
 */
final class SubclassFileWriter extends FileWriter
{
    private readonly Entity $entity;
    private readonly string $class;
    private readonly string $namespace;
    private readonly ExistingClassParser $existingClassParser;
    private readonly UseStatementGenerator $useStatementGenerator;
    private readonly PropertyDocBlockGenerator $propertyDocBlockGenerator;
    private readonly PropertyBlockGenerator $propertyBlockGenerator;
    private readonly MagicMethodDocGenerator $magicMethodDocGenerator;
    private readonly AuthorizableCodeGenerator $authorizableCodeGenerator;
    private readonly AuthorizableRoleCodeGenerator $authorizableRoleCodeGenerator;
    private readonly AuthorizationCodeGenerator $authorizationCodeGenerator;
    private readonly ClassDeclarationInjector $declarationInjector;
    private readonly ClassFileAssembler $fileAssembler;
    private readonly PropertyAttributeGenerator $accessControlGenerator;

    /**
     * @param URL $url The destination file this writer generates.
     * @param Entity $entity The entity the generated subclass models.
     * @param string $class The unqualified class name to generate.
     * @param string $namespace The namespace the generated class belongs to.
     * @param Closure $classNameGenerator Resolves the class name for a given entity, used for related-entity type hints.
     */
    public function __construct(URL $url, Entity $entity, string $class, string $namespace, Closure $classNameGenerator)
    {
        parent::__construct($url);
        $this->namespace = $namespace;
        $this->class = $class;
        $this->entity = $entity;
        $this->existingClassParser = new ExistingClassParser();
        $this->useStatementGenerator = new UseStatementGenerator();
        $accessControlGenerator = new PropertyAttributeGenerator();
        $this->accessControlGenerator = $accessControlGenerator;
        $this->propertyDocBlockGenerator = new PropertyDocBlockGenerator($accessControlGenerator);
        $this->propertyBlockGenerator = new PropertyBlockGenerator($accessControlGenerator);
        $this->authorizableCodeGenerator = new AuthorizableCodeGenerator($accessControlGenerator);
        $this->authorizableRoleCodeGenerator = new AuthorizableRoleCodeGenerator($accessControlGenerator);
        $this->authorizationCodeGenerator = new AuthorizationCodeGenerator($accessControlGenerator);
        $this->magicMethodDocGenerator = new MagicMethodDocGenerator($classNameGenerator);
        $this->declarationInjector = new ClassDeclarationInjector();
        $this->fileAssembler = new ClassFileAssembler();
    }

    #[Override]
    public string $contents {
        /**
         * @throws Exception
         */
        get {
            $parsed = $this->existingClassParser->parse($this->url);
            $existingUses = $parsed["uses"];
            $existingProperties = $parsed["properties"];
            $existingClassProperties = $parsed["classProperties"];
            $existingMethods = $parsed["methods"];
            $declaration = $parsed["declaration"] ?? $this->fileAssembler->createDefaultDeclaration($this->class, $this->entity);
            $reservedPropertyNames = match (true) {
                $this->entity->isAuthorizable => $this->authorizableCodeGenerator->reservedPropertyNames,
                $this->entity->isAuthorizableRole => $this->authorizableRoleCodeGenerator->reservedPropertyNames,
                $this->entity->isAuthorization => $this->authorizationCodeGenerator->reservedPropertyNames,
                default => [],
            };
            $uses = $this->useStatementGenerator->generate($this->entity, $existingUses);
            $properties = $this->propertyDocBlockGenerator->generate($this->entity, $existingProperties, $reservedPropertyNames, $declaration);
            $methods = $this->magicMethodDocGenerator->generate($this->entity, $this->namespace);
            $propertyBlocks = $this->propertyBlockGenerator->generate($this->entity, $uses, $declaration, $reservedPropertyNames);
            if ($this->entity->isAuthorizable) {
                $propertyBlocks->appendContentsOf($this->authorizableCodeGenerator->generatePropertyBlocks($this->entity, $existingClassProperties, $uses));
                if ($method = $this->authorizableCodeGenerator->generateDefaultRepresentationMethod($this->entity, $existingMethods)) {
                    $propertyBlocks->append(new class($method) extends ObjectClass {
                        public string $description {
                            get => $this->code;
                        }

                        public function __construct(private readonly string $code)
                        {
                        }
                    });
                }
            } elseif ($this->entity->isAuthorizableRole) {
                $propertyBlocks->appendContentsOf($this->authorizableRoleCodeGenerator->generatePropertyBlocks($this->entity, $existingClassProperties, $uses));
            } elseif ($this->entity->isAuthorization) {
                $propertyBlocks->appendContentsOf($this->authorizationCodeGenerator->generatePropertyBlocks($this->entity, $existingClassProperties, $uses));
            }
            $classAttributes = $this->accessControlGenerator->generateFieldAttributes($this->entity->accessControls, $uses);
            return $this->fileAssembler->assemble($this->namespace, $this->entity, $uses, $properties, $methods, $this->declarationInjector->inject($declaration, $propertyBlocks), $classAttributes);
        }
    }
}
