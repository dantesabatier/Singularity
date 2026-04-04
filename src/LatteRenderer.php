<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App;

use App\Model\AccessControl;
use App\Model\Attribute;
use App\Model\Entity;
use App\Model\FetchedProperty;
use App\Model\FetchIndex;
use App\Model\FetchIndexElement;
use App\Model\FetchRequestTemplate;
use App\Model\Model;
use App\Model\Project;
use App\Model\Relationship;
use App\Model\Role;
use App\Model\UniquenessConstraint;
use Exception;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Override;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Service\Renderer;
use function Sabatier\Foundation\camelcase;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\substring_to_index;

final class LatteRenderer extends Renderer
{
    private Engine $engine {
        /**
         * @throws Exception
         */
        get {
            if (isset($this->engine)) {
                return $this->engine;
            }
            $engine = new Engine();
            $engine->addFilter("readable", fn(mixed $value): string => human_readable_value($value));
            $engine->addFilter("camelCase", fn(string $value): string => camelcase($value));
            $engine->addFilter("firstLower", lcfirst(...));
            $engine->addFilter("coerced", fn(mixed $value, int $type): mixed => ManagedObject::coercedValue($value, AttributeType::from($type)));
            $engine->addFilter("nonempty", fn(string $value): ?string => $value === "" ? null : $value);
            $engine->addFilter("json", json_encode(...));
            $engine->addFunction("img", $this->image(...));
            $engine->addFunction("vite_asset", fn(): array => $this->viteAsset);
            $engine->addFunction("localized_string", fn(string $value): string => localized_string($value));
            $engine->setCacheDirectory(FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->path);
            $engine->setLoader(new FileLoader($this->bundle->resourceURL?->appendingPathComponent("Views")?->path));
            return $this->engine = $engine;
        }
    }
    /** @var array{client: ?string, css: list<string>, js: list<string>} */
    private array $viteAsset {
        /**
         * @throws Exception
         */
        get => $this->viteAsset ??= $this->resolveViteAsset("Frontend/ts/main.ts");
    }

    /**
     * @return array{client: ?string, css: list<string>, js: list<string>}
     * @throws Exception
     */
    private function resolveViteAsset(string $entry): array
    {
        /** @var string $devServer */
        $devServer = ProcessInfo::processInfo()->environment[ViteDevServerEnvironmentKey] ?? "";
        if ($devServer !== "") {
            return [
                "client" => "$devServer/@vite/client",
                "css" => ["$devServer/Frontend/scss/main.scss"],
                "js" => ["$devServer/$entry"],
            ];
        }
        $fileManager = FileManager::default();
        $manifestURL = $fileManager->documentRootDirectory->appendingPathComponent("Build")->appendingPathComponent(".vite")->appendingPathComponent("manifest.json");
        if (!$fileManager->fileExists($manifestURL->path, $isDirectory) || $isDirectory || !$fileManager->isReadableFile($manifestURL->path)) {
            return [
                "client" => null,
                "css" => [],
                "js" => [],
            ];
        }
        /** @var array<string, array{file: string, css?: list<string>|null}>|null $manifest */
        $manifest = json_decode($fileManager->contents($manifestURL->path) ?? "[]", true);
        $item = $manifest[$entry] ?? null;
        if (!is_array($item) || !isset($item["file"])) {
            return [
                "client" => null,
                "css" => [],
                "js" => [],
            ];
        }
        /** @var list<string> $css */
        $css = $item["css"] ?? [];
        return [
            "client" => null,
            "css" => array_map(fn(string $file): string => "/Build/$file", $css),
            "js" => ["/Build/" . $item["file"]],
        ];
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
        return match ($object::class) {
            Project::class, Model::class, AccessControl::class, Role::class => class_name($object::class),
            Entity::class => "E",
            Attribute::class => $this->name($object->type),
            Relationship::class => $object->isToMany ? "M" : "O",
            FetchedProperty::class, FetchRequestTemplate::class => "F",
            FetchIndex::class => "I",
            FetchIndexElement::class => ($property = $object->property) ? $this->image($property) : $this->name(AttributeType::undefined),
            UniquenessConstraint::class => "U",
            default => substring_to_index($object->entity->name, 1)
        };
    }

    #[Override]
    public function render(string $name, object|array $context): string
    {
        return $this->engine->renderToString("$name.latte", $context);
    }
}
