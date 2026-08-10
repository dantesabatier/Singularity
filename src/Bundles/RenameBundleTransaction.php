<?php

declare(strict_types=1);

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\PersistentStoreType;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\ManagedObjectModelURLOption;
use const Sabatier\Foundation\kCFBundleNameKey;

final readonly class RenameBundleTransaction implements Transaction
{
    /**
     * @param Project $project The project whose bundle is renamed.
     * @param string $newName The new bundle name.
     */
    public function __construct(private Project $project, private string $newName)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $url = $this->project->url ?? fatal_error("Project URL is required to rename a bundle");
        $bundle = Bundle::bundleWithURL($url);
        $oldName = $bundle->object(kCFBundleNameKey);
        $oldName !== $this->newName ?: throw new Exception("Bundle already has this name");
        $this->autoloadIfNeeded($bundle);
        [$sourceModelURL, $destinationModelURL] = $this->resolveModelURLs($bundle, $oldName);
        $this->migrateStore($oldName, $this->newName, $sourceModelURL, $destinationModelURL);
        $this->updateInfoPlist($bundle);
        $this->project->name = $this->newName;
        FileManager::default()->removeItem($sourceModelURL);
    }

    private function autoloadIfNeeded(Bundle $bundle): void
    {
        $autoload = $bundle->bundleURL->appendingPathComponent("vendor")->appendingPathComponent("autoload")->appendingPathExtension("php")->path;
        if (FileManager::default()->fileExists($autoload)) {
            require_once $autoload;
        }
    }

    /**
     * @param Bundle $bundle
     * @param string $oldName
     * @return array{URL, URL}
     * @throws Exception
     */
    private function resolveModelURLs(Bundle $bundle, string $oldName): array
    {
        $resourceURL = $bundle->resourceURL ?? throw new Exception("Invalid resources URL");
        $destination = $resourceURL->appendingPathComponent($this->newName)->appendingPathExtension("mom");
        $source = $resourceURL->appendingPathComponent($oldName)->appendingPathExtension("mom");
        if (!FileManager::default()->fileExists($source->path)) {
            throw new Exception("Source model not found");
        }
        FileManager::default()->copyItem($source, $destination);
        return [$source, $destination];
    }

    /**
     * @throws Exception
     */
    private function migrateStore(string $oldName, string $newName, URL $sourceModelURL, URL $destinationModelURL): void
    {
        $model = new ManagedObjectModel($sourceModelURL);
        $coordinator = new PersistentStoreCoordinator($model);
        $coordinator->replacePersistentStore(new URL("sql://$newName"), new Dictionary([ManagedObjectModelURLOption => $destinationModelURL]), new URL("sql://$oldName"), new Dictionary([ManagedObjectModelURLOption => $sourceModelURL]), PersistentStoreType::sql);
    }

    private function updateInfoPlist(Bundle $bundle): void
    {
        /** @var Dictionary<mixed> $info */
        $info = $bundle->infoDictionary;
        $info[kCFBundleNameKey] = $this->newName;
        PropertyListSerialization::writePropertyList($info, $bundle->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
    }
}
