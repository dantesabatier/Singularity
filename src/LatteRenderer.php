<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\Project;
use App\Model\Property;
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
            "engine" => (function () {
                $engine = new Engine();
                $engine->addFilter("readable", fn(mixed $value): string => human_readable_value($value));
                /** @psalm-suppress InternalMethod */
                $engine->addFilter("coerced", fn(mixed $value, int $type): mixed => ManagedObject::coercedValue($value, AttributeType::from($type)));
                $engine->addFilter("nonempty", fn(string $value): ?string => $value === "" ? null : $value);
                $engine->addFilter("json", fn(mixed $value): string => json_encode($value));
                $fn = fn(AttributeType $type): string => match ($type) {
                    AttributeType::integer16, AttributeType::integer32, AttributeType::integer64, AttributeType::decimal, AttributeType::double, AttributeType::float => "N",
                    AttributeType::uuid, AttributeType::undefined => $type->name,
                    default => strtoupper(substring_to_index($type->name, 1))
                };
                $img = function (Project|Entity|UniquenessConstraint|Property|FetchIndex|FetchIndexElement $e) use (&$img, &$fn): string {
                    return match (true) {
                        $e instanceof Project => "P",
                        $e instanceof Entity => "E",
                        $e instanceof UniquenessConstraint => "U",
                        $e instanceof Attribute => $fn($e->type),
                        $e instanceof Relationship => $e->isToMany ? "M" : "O",
                        $e instanceof FetchIndex => "I",
                        $e instanceof FetchIndexElement => ($property = $e->property) ? $img($property) : $fn(AttributeType::undefined),
                        default => substring_to_index($e->entity->name, 1)
                    };
                };
                $engine->addFunction("img", $img);
                $engine->addFunction("localized", fn(string $value): string => localized_string($value));
                try {
                    $engine->setTempDirectory(FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->path);
                } catch (Exception) {
                }
                $engine->setLoader(new FileLoader($this->bundle->resourceURL?->appendingPathComponent("Views")?->path));
                return $engine;
            })(),
            default => throw new Exception("Unknown property $name")
        };
    }

    #[Override]
    public function render(string $name, object|array $context): string
    {
        return $this->engine->renderToString("$name.latte", $context);
    }
}
