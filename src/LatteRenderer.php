<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\Attribute;
use App\Model\FetchIndexElement;
use App\Model\Property;
use App\Model\Relationship;
use Exception;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Service\Renderer;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\substring_to_index;

class LatteRenderer extends Renderer
{
    public readonly Engine $engine;

    public function __construct(Bundle $bundle)
    {
        parent::__construct($bundle);
        $this->engine = new Engine();
        $this->engine->addFilter("readable", fn(mixed $value): string => human_readable_value($value));
        /** @psalm-suppress InternalMethod */
        $this->engine->addFilter("coerced", fn(mixed $value, int $type): mixed => ManagedObject::coercedValue($value, AttributeType::from($type)));
        $this->engine->addFilter("nonempty", fn(string $value): ?string => $value === "" ? null : $value);
        $this->engine->addFilter("json", fn(mixed $value): string => json_encode($value));
        $img = function (Property|FetchIndexElement $e) use (&$img): string {
            if ($e instanceof Attribute) {
                $type = AttributeType::from($e->type);
                return match ($type) {
                    AttributeType::integer16, AttributeType::integer32, AttributeType::integer64, AttributeType::decimal, AttributeType::double, AttributeType::float => "N",
                    default => strtoupper(substring_to_index($type->name, 1))
                };
            } elseif ($e instanceof Relationship) {
                return $e->isToMany ? "M" : "O";
            } elseif ($e instanceof FetchIndexElement) {
                if ($property = $e->property) {
                    return $img($property);
                }
            }
            return substring_to_index($e::className(), 1);
        };
        $this->engine->addFunction("img", $img);
        try {
            $this->engine->setTempDirectory(FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->path);
        } catch (Exception) {
        }
        $this->engine->setLoader(new FileLoader($bundle->resourceURL?->path));
    }

    public function render(string $name, object|array $context): string
    {
        return $this->engine->renderToString("$name.latte", $context);
    }
}
