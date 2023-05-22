<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

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
