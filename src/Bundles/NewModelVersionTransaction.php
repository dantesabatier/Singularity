<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\ModelFileWriter;
use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use function Sabatier\Foundation\fatal_error;

/**
 * Freezes the model a project is working on and opens the next version for editing.
 *
 * Versioning is not automatic: starting a version is the same act as deciding to migrate, so the
 * programmer asks for it. Saving stays saving — it emits no version and freezes nothing.
 *
 * The frozen version is a file, not a row. The store keeps one editable model, which is always the
 * work in progress and always the destination of a migration; every version before it lives inside
 * the package, and its only role is to be a migration's source. So this transaction writes the model
 * as it currently stands, records its checksum, and leaves the same row in the store as the version
 * that follows.
 *
 * A project that never asked for a version keeps a lone model file. The first version is what turns
 * that into a package, which is why the file moves inside and keeps the name it had: that name is an
 * identity a production store may already record against its checksum.
 */
final readonly class NewModelVersionTransaction implements Transaction
{
    /** @var string The name of the version that was frozen. */
    public string $frozenVersionName;
    /** @var string The name of the version now open for editing. */
    public string $currentVersionName;

    /**
     * @param Project $project The project whose model is versioned.
     */
    public function __construct(private Project $project)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $model = $this->project->model ?? fatal_error("Project has no model to version");
        $bundleURL = $this->project->url ?? fatal_error("Project URL is required to version a model");
        $name = $this->project->name;
        $modelBundle = new ModelBundle($bundleURL, $name);
        $versionChecksums = $modelBundle->versionChecksums;
        $this->createPackageIfNeeded($modelBundle);
        // The frozen version takes the numbered name and the work in progress keeps the package's own, so a package missing its version information still resolves: the current version is the one named after it.
        $frozenVersionName = $this->nextVersionName($name, $versionChecksums);
        $currentVersionName = $name;
        new ModelFileWriter($modelBundle->urlForVersionNamed($frozenVersionName), $model)->save();
        $versionChecksums[$frozenVersionName] = $model->managedObjectModel->versionChecksum;
        $versionChecksums[$currentVersionName] = $model->managedObjectModel->versionChecksum;
        $modelBundle->writeVersionInfo($currentVersionName, $versionChecksums);
        // The row carries on as the version that follows, and its file has to exist right away: a package whose version information names a version it does not hold has no current version at all, so the project would stop loading until the next save.
        new ModelFileWriter($modelBundle->urlForVersionNamed($currentVersionName), $model)->save();
        $this->frozenVersionName = $frozenVersionName;
        $this->currentVersionName = $currentVersionName;
    }

    /**
     * Turns a lone model file into a package, carrying the file inside under the name it already had.
     * @param ModelBundle $modelBundle Where the project keeps its model.
     * @throws Exception
     */
    private function createPackageIfNeeded(ModelBundle $modelBundle): void
    {
        if ($modelBundle->isVersioned) {
            return;
        }
        $fileManager = FileManager::default();
        $fileManager->createDirectory($modelBundle->packageURL, true);
        $modelFileURL = $modelBundle->modelFileURL;
        if ($fileManager->fileExists($modelFileURL->path)) {
            $fileManager->moveItem($modelFileURL, $modelBundle->urlForVersionNamed($modelFileURL->deletingPathExtension()->lastPathComponent));
        }
    }

    /**
     * Returns a numbered version name no version holds yet. Numbering starts at two because the
     * unnumbered name belongs to the work in progress.
     * @param string $name The bundle's name, which every version is named after.
     * @param Dictionary<string> $versionChecksums The version checksum of every version so far, keyed by version name.
     */
    private function nextVersionName(string $name, Dictionary $versionChecksums): string
    {
        $version = 2;
        while ($versionChecksums->valueForKey("$name $version") !== null) {
            $version++;
        }
        return "$name $version";
    }
}
