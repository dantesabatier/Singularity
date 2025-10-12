<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Attribute;
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
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Service\Renderer;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\substring_to_index;

final class LatteRenderer extends Renderer
{
    private(set) Engine $engine {
        get => $this->engine ??= $this->initializeEngine();
    }

    /**
     * @throws Exception
     */
    private function initializeEngine(): Engine
    {
        $engine = new Engine();
        $engine->addFilter("readable", fn(mixed $value): string => human_readable_value($value));
        $engine->addFilter("camelCase", fn(string $value): string => (string)preg_replace_callback("/\s(.)/", fn(array $matches) => strtoupper($matches[1]), $value));
        $engine->addFilter("firstLower", fn(string $value): string => lcfirst($value));
        $engine->addFilter("coerced", fn(mixed $value, int $type): mixed => ManagedObject::coercedValue($value, AttributeType::from($type)));
        $engine->addFilter("nonempty", fn(string $value): ?string => $value === "" ? null : $value);
        $engine->addFilter("json", fn(mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR));
        $engine->addFunction("img", fn(ManagedObject $object): string => $this->image($object));
        $engine->addFunction("localized", fn(string $value): string => localized_string($value));
        $engine->setTempDirectory(FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->path);
        $engine->setLoader(new FileLoader($this->bundle->resourceURL?->appendingPathComponent("Views")?->path));
        return $engine;
    }

    private function name(AttributeType $type): string
    {
        return match ($type) {
            AttributeType::integer16, AttributeType::integer32, AttributeType::integer64, AttributeType::decimal, AttributeType::double, AttributeType::float => "N",
            AttributeType::uuid, AttributeType::undefined => $type->name,
            default => strtoupper(substring_to_index($type->name, 1))
        };
    }

    private function image(ManagedObject $object): string
    {
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
            default => substring_to_index($object->entity->name, 1)
        };
    }

    #[Override]
    public function render(string $name, object|array $context): string
    {
        return $this->engine->renderToString("$name.latte", $context);
    }
}
