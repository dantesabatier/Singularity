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
        $this->project->autoloadBundle();
        $sourceBundle = new ModelBundle($bundle->bundleURL, $oldName);
        $destinationBundle = new ModelBundle($bundle->bundleURL, $this->newName);
        $this->copyModel($sourceBundle, $destinationBundle);
        // Only the package is renamed: a version's name is an identity a production store may already record against its checksum, so the copy keeps the names it held and the current one is still whatever its version information names.
        $destinationModelURL = $sourceBundle->isVersioned ? $destinationBundle->urlForVersionNamed($sourceBundle->currentVersionName) : $destinationBundle->modelFileURL;
        $this->migrateStore($oldName, $this->newName, $sourceBundle->currentVersionURL, $destinationModelURL);
        $this->updateInfoPlist($bundle);
        $this->project->name = $this->newName;
        FileManager::default()->removeItem($sourceBundle->url);
    }

    /**
     * Copies the model to its new name, carrying every version a versioned project holds.
     * @param ModelBundle $sourceBundle Where the model lives under the old name.
     * @param ModelBundle $destinationBundle Where it lives under the new one.
     * @throws Exception
     */
    private function copyModel(ModelBundle $sourceBundle, ModelBundle $destinationBundle): void
    {
        if (!FileManager::default()->fileExists($sourceBundle->url->path)) {
            throw new Exception("Source model not found");
        }
        FileManager::default()->copyItem($sourceBundle->url, $sourceBundle->isVersioned ? $destinationBundle->packageURL : $destinationBundle->modelFileURL);
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
