<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Attribute;
use App\Model\Configuration;
use App\Model\Entity;
use App\Model\FetchedProperty;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Model;
use App\Model\Project;
use App\Model\Relationship;
use App\Model\UniquenessConstraint;
use Exception;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Service\Renderer;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\substring_to_index;

class LatteRenderer extends Renderer
{
    public readonly Engine $engine;

    public function __construct(Bundle $bundle)
    {
        parent::__construct($bundle);
        unset($this->engine);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "engine" => $this->engine(),
            default => throw new Exception("Unknown property $name")
        };
    }

    private function name(AttributeType $type): string
    {
        return match ($type) {
            AttributeType::integer16, AttributeType::integer32, AttributeType::integer64, AttributeType::decimal, AttributeType::double, AttributeType::float => "N",
            AttributeType::uuid, AttributeType::undefined => $type->name,
            default => strtoupper(substring_to_index($type->name, 1))
        };
    }

    private function image(Project|Model|Entity|Attribute|Relationship|FetchedProperty|FetchIndex|FetchIndexElement|UniquenessConstraint|FetchRequestTemplate|Configuration $object): string
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return match (true) {
            $object instanceof Project => "Project",
            $object instanceof Model => "Model",
            $object instanceof Entity => "E",
            $object instanceof Attribute => $this->name($object->type),
            $object instanceof Relationship => $object->isToMany ? "M" : "O",
            $object instanceof FetchedProperty, $object instanceof FetchRequestTemplate => "F",
            $object instanceof FetchIndex => "I",
            $object instanceof FetchIndexElement => ($property = $object->property) ? $this->image($property) : $this->name(AttributeType::undefined),
            $object instanceof UniquenessConstraint => "U",
            $object instanceof Configuration => "C",
            default => substring_to_index($object->entity->name, 1)
        };
    }

    /**
     * @throws Exception
     */
    private function engine(): Engine
    {
        $engine = new Engine();
        $engine->addFilter("readable", fn(mixed $value): string => human_readable_value($value));
        /** @psalm-suppress ArgumentTypeCoercion */
        $engine->addFilter("className", fn(string $value): string => class_name($value));
        $engine->addFilter("camelCase", fn(string $value): string => preg_replace_callback("/\s(.)/", fn(array $matches) => strtoupper($matches[1]), $value));
        $engine->addFilter("firstLower", fn(string $value): string => lcfirst($value));
        /** @psalm-suppress InternalMethod */
        $engine->addFilter("coerced", fn(mixed $value, int $type): mixed => ManagedObject::coercedValue($value, AttributeType::from($type)));
        $engine->addFilter("nonempty", fn(string $value): ?string => $value === "" ? null : $value);
        $engine->addFilter("json", fn(mixed $value): string => json_encode($value));
        $engine->addFunction("img", fn(Project|Model|Entity|Attribute|Relationship|FetchedProperty|FetchIndex|FetchIndexElement|UniquenessConstraint|FetchRequestTemplate|Configuration $object): string => $this->image($object));
        $engine->addFunction("localized", fn(string $value): string => localized_string($value));
        $engine->setTempDirectory(FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->path);
        $engine->setLoader(new FileLoader($this->bundle->resourceURL?->appendingPathComponent("Views")?->path));
        return $engine;
    }

    #[Override]
    public function render(string $name, object|array $context): string
    {
        return $this->engine->renderToString("$name.latte", $context);
    }
}
