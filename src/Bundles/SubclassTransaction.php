<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\SubclassFileWriter;
use App\Model\Entity;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Override;
use ReflectionClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\fatal_error;

final readonly class SubclassTransaction implements Transaction
{
    public function __construct(private Project $project)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        /** @var URL $url */
        $url = $this->project->url;
        /** @var Model $model */
        $model = $this->project->model;
        $directory = "Model";
        $bundle = Bundle::bundleWithURL($url);
        $principalClass = $bundle->principalClass ?? fatal_error("Unable to load the application principal class");
        $reflectionClass = new ReflectionClass($principalClass);
        $namespace = "{$reflectionClass->getNamespaceName()}\\$directory";
        $fileManager = FileManager::default();
        $sourcesURL = $bundle->bundleURL->appendingPathComponent("src");
        $directoryURL = $sourcesURL->appendingPathComponent($directory);
        if (!$fileManager->fileExists($directoryURL->path)) {
            $fileManager->createDirectory($directoryURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        foreach ($model->entities as $entity) {
            $class = $this->className($entity, $namespace);
            $fileURL = $directoryURL->appendingPathComponent($class)->appendPathExtension("php");
            $fileWriter = new SubclassFileWriter($fileURL, $entity, $class, $namespace, fn(Entity $entity, string $namespace): string => $this->className($entity, $namespace));
            $fileWriter->save();
            $entity->managedObjectClassName = "$namespace\\$class";
        }
    }

    private function className(Entity $entity, string $namespace): string
    {
        /** @var class-string $class */
        $class = $entity->managedObjectClassName ?? $entity->name;
        if (!str_contains($class, "\\")) {
            /** @var class-string $class */
            $class = "$namespace\\$class";
        }
        return class_name($class);
    }
}
