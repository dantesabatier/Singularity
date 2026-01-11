<?php

namespace App\FileWriters;

use App\Model\AccessControl;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchedProperty;
use App\Model\Property;
use App\Model\Relationship;
use App\Model\Role;
use Closure;
use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\AuthorizationScope;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/**
 * @psalm-type SubclassNameGenerator = Closure(Entity, string): string
 */
final class SubclassFileWriter extends FileWriter
{
    public string $contents {
        /**
         * @throws Exception
         */
        get {
            $entity = $this->entity;
            $namespace = $this->namespace;
            $class = $this->class;
            $fileURL = $this->url;
            $setClassName = class_name(Set::class);
            $arrayClassName = class_name(ArrayClass::class);
            $scopeClass = class_name(AuthorizationScope::class);
            $superentity = $entity->superentity;
            $parts = new ArrayClass();
            $content = "<?php\n\n";
            $content .= "namespace $namespace;\n";
            if (!$superentity) {
                $content .= "\n";
            }
            /** @var Set<Attribute> $attributes */
            $attributes = new Set($entity->attributes->sorted([new SortDescriptor("position")]));
            /** @var Set<string> $uses */
            $uses = $attributes->compactMap(function (Attribute $attribute): ?string {
                $attributeValueClassName = match ($attribute->type) {
                    AttributeType::date => Date::class,
                    AttributeType::uuid => UUID::class,
                    AttributeType::uri => URL::class,
                    AttributeType::objectID => ManagedObjectID::class,
                    AttributeType::compositeAttributeType => Dictionary::class,
                    default => $attribute->attributeValueClassName
                };
                if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
                    return "use $attributeValueClassName;";
                }
                return null;
            });
            $relationships = $entity->relationships->sorted([new SortDescriptor("position")]);
            if ($relationships->contains(fn(Relationship $relationship): bool => $relationship->isToMany)) {
                $uses->insert("use " . Set::class . ";");
            }
            $fetchedProperties = $entity->fetchedProperties->sorted([new SortDescriptor("position")]);
            if (!$fetchedProperties->isEmpty) {
                $uses->insert("use " . ArrayClass::class . ";");
            }
            if (!$superentity) {
                $uses->insert("use " . ManagedObject::class . ";");
            }
            $superclass = $superentity?->name ?? class_name(ManagedObject::class);
            /** @var Set<string> $properties */
            $properties = new Set();
            $path = $fileURL->path;
            if (FileManager::default()->fileExists($path) && ($contents = FileManager::default()->contents($path)) && ($index = strpos($contents, "class"))) {
                $array = preg_split(sprintf("/%s/", preg_quote("\n", "/")), substring_to_index($contents, $index), -1, PREG_SPLIT_NO_EMPTY);
                $uses->formUnion(array_map(rtrim(...), array_filter($array, fn(string $e): bool => str_starts_with($e, "use"))));
                $properties->formUnion(array_map(rtrim(...), array_filter($array, fn(string $e): bool => str_starts_with($e, " * @property"))));
                $declaration = substring_from_index($contents, $index);
            } else {
                $declaration = "class $class extends $superclass\n{\n}\n";
            }
            $handleAccessControl = function (Property $property, string $type, bool $isOptional) use ($uses, $parts, $declaration, $scopeClass): bool {
                /** @var Set<AccessControl> $accessControls */
                $accessControls = $property->accessControls;
                if ($accessControls->isEmpty) {
                    return false;
                }
                if (str_contains($declaration, "\$$property->name")) {
                    return true;
                }
                $phpAttributes = $accessControls->map(function (AccessControl $accessControl) use ($uses, $scopeClass): string {
                    $uses->insert("use Sabatier\\Service\\$accessControl->name;");
                    $uses->insert("use " . AuthorizationScope::class . ";");
                    if ($accessControl->roles->isEmpty) {
                        return "#[$accessControl->name]";
                    }
                    return "#[$accessControl->name({$accessControl->roles->map(fn(Role $role) => "\"$role->name\"")}, $scopeClass::{$accessControl->scope->name})]";
                })->join("\n    ");
                $nullable = $isOptional ? "|null" : "";
                $parts->append(<<<PHP
                $phpAttributes
                    public $type$nullable \$$property->name {
                        get => \$this->valueForKey(__PROPERTY__);
                        set {
                            \$this->setValueForKey(\$value, __PROPERTY__);
                        }
                    }
                PHP
                );
                return true;
            };
            /** @psalm-suppress InvalidArgument */
            $properties->formUnion($attributes->compactMap(function (Attribute $attribute) use ($properties, $handleAccessControl): ?string {
                $attributeValueClassName = match ($attribute->type) {
                    AttributeType::date => Date::class,
                    AttributeType::uuid => UUID::class,
                    AttributeType::uri => URL::class,
                    AttributeType::objectID => ManagedObjectID::class,
                    AttributeType::compositeAttributeType => Dictionary::class,
                    default => $attribute->attributeValueClassName
                };
                if ($attributeValueClassName !== null && class_exists($attributeValueClassName)) {
                    $attributeValueClassName = class_name($attributeValueClassName);
                    if ($attribute->type === AttributeType::compositeAttributeType) {
                        $attributeValueClassName .= "<mixed>";
                    }
                }
                if (!($type = match ($attribute->type) {
                    AttributeType::transformable => "mixed",
                    AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => "int",
                    AttributeType::decimal, AttributeType::double => "double",
                    AttributeType::float => "float",
                    AttributeType::binaryData, AttributeType::string => "string",
                    AttributeType::boolean => "bool",
                    default => $attributeValueClassName,
                })) {
                    return null;
                }
                if ($handleAccessControl($attribute, $type, $attribute->isOptional && $type !== "mixed")) {
                    return null;
                }
                if ($properties->contains(fn(string $e): bool => str_ends_with($e, "\$$attribute->name"))) {
                    return null;
                }
                $string = " * @property";
                if ($attribute->isDerived) {
                    $string .= "-read";
                }
                $string .= " $type";
                $minValue = $attribute->minValue;
                $maxValue = $attribute->maxValue;
                if (match ($attribute->type) {
                        AttributeType::integer16, AttributeType::integer32, AttributeType::integer64 => true,
                        default => false
                    } && ($minValue !== null || $maxValue !== null)) {
                    $string .= sprintf("<%s, %s>", $minValue ?? "min", $maxValue ?? "max");
                }
                if ($attribute->isOptional && $type !== "mixed") {
                    $string .= "|null";
                }
                return "$string \$$attribute->name";
            }));
            $properties->formUnion($relationships->compactMap(function (Relationship $relationship) use ($setClassName, $handleAccessControl): ?string {
                $lazyDestinationEntityName = $relationship->lazyDestinationEntityName;
                $className = $relationship->isToMany ? "$setClassName" : $lazyDestinationEntityName;
                if ($handleAccessControl($relationship, $className, $relationship->isOptional)) {
                    return null;
                }
                $string = " * @property ";
                $string .= $className;
                if ($relationship->isToMany) {
                    $string .= "<$lazyDestinationEntityName>";
                }
                if ($relationship->isOptional) {
                    $string .= "|null";
                }
                return "$string \$$relationship->name";
            }));
            $properties->formUnion($fetchedProperties->compactMap(function (FetchedProperty $fetchedProperty) use ($arrayClassName, $handleAccessControl): ?string {
                $type = "$arrayClassName";
                if ($handleAccessControl($fetchedProperty, $type, false)) {
                    return null;
                }
                return " * @property-read $type<$fetchedProperty->fetchRequestEntityName> \$$fetchedProperty->name";
            }));
            if (!$uses->isEmpty) {
                if ($superentity) {
                    $content .= "\n";
                }
                $content .= $uses->sort()->join("\n");
            }
            $content .= "\n";
            /** @var ArrayClass<string> $methods */
            $methods = $relationships->compactMap(function (Relationship $relationship) use ($namespace, $setClassName): ?string {
                if (!$relationship->isToMany || !($destinationEntity = $relationship->destinationEntity)) {
                    return null;
                }
                $relationshipName = ucfirst($relationship->name);
                $entityClassName = ($this->classNameGenerator)($destinationEntity, $namespace);
                return new ArrayClass([
                    " * @method void add{$relationshipName}Object($entityClassName \$object)",
                    " * @method void remove{$relationshipName}Object($entityClassName \$object)",
                    " * @method void add$relationshipName($setClassName \$objects)",
                    " * @method void remove$relationshipName($setClassName \$objects)",
                    " * @method $setClassName<$entityClassName> intersect$relationshipName($setClassName \$objects)",
                    " * @method void set$relationshipName($setClassName \$objects)"
                ])->join("\n");
            });
            if (!$uses->isEmpty) {
                $content .= "\n";
            }
            if (!$properties->isEmpty) {
                $content .= "/**\n";
                $content .= $properties->join("\n");
                if (!$methods->isEmpty) {
                    $content .= "\n";
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
            if (!$parts->isEmpty) {
                $position = strpos($declaration, "{");
                if ($position !== false) {
                    $index = $position + 1;
                    $declarationStart = substring_to_index($declaration, $index);
                    $declarationEnd = substring_from_index($declaration, $index);
                    $injection = "\n    {$parts->join("\n\n    ")}";
                    $declaration = $declarationStart . $injection . $declarationEnd;
                }
            }
            return $content . $declaration;
        }
    }
    private readonly Entity $entity;
    private readonly string $class;
    private readonly string $namespace;
    /** @var SubclassNameGenerator */
    private readonly Closure $classNameGenerator;

    public function __construct(URL $url, Entity $entity, string $class, string $namespace, Closure $classNameGenerator)
    {
        parent::__construct($url);
        $this->namespace = $namespace;
        $this->class = $class;
        $this->entity = $entity;
        $this->classNameGenerator = $classNameGenerator;
    }
}
