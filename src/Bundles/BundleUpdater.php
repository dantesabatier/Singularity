<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\DelegateFileWriter;
use App\FileWriters\DotEnvFileWriter;
use App\FileWriters\ModelFileWriter;
use App\FileWriters\ModelMapFileWriter;
use App\Model\ModelMap;
use App\Model\Project;
use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;

final readonly class BundleUpdater
{
    /**
     * @param Project $project The project whose generated bundle is brought up to date.
     */
    public function __construct(private Project $project)
    {
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $this->updateModelIfNeeded();
        $this->updateModelMaps();
        $this->updateEnvIfNeeded();
        $this->updateDelegateIfNeeded();
    }

    /**
     * @throws Exception
     */
    private function updateModelIfNeeded(): void
    {
        $model = $this->project->model;
        if (!$model) {
            return;
        }
        $bundleURL = $this->project->url ?? throw new Exception("Project has no bundle URL");
        $modelBundle = new ModelBundle($bundleURL, $bundleURL->lastPathComponent);
        new ModelFileWriter($modelBundle->currentVersionURL, $model)->save();
        if (!$modelBundle->isVersioned) {
            return;
        }
        $currentVersionName = $modelBundle->currentVersionName;
        $versionChecksums = $modelBundle->versionChecksums;
        $versionChecksums[$currentVersionName] = $model->managedObjectModel->versionChecksum;
        $modelBundle->writeVersionInfo($currentVersionName, $versionChecksums);
        new CachedModelWriter($this->project)->write($model->managedObjectModel);
    }

    /**
     * Emits a mapping model per map that declares the version it starts from.
     *
     * A map without a source has no pair to migrate between, and the archive carries both models, so
     * there is nothing to write yet.
     * @throws Exception
     */
    private function updateModelMaps(): void
    {
        $bundleURL = $this->project->url ?? throw new Exception("Project has no bundle URL");
        $modelBundle = new ModelBundle($bundleURL, $bundleURL->lastPathComponent);
        $this->project->modelMaps->forEach(function (ModelMap $modelMap) use ($modelBundle): void {
            if ($modelMap->sourceModelURL) {
                new ModelMapFileWriter($modelBundle->urlForMappingModelNamed($modelMap->name), $modelMap)->save();
            }
        });
    }

    /**
     * @throws Exception
     */
    private function updateEnvIfNeeded(): void
    {
        $bundleURL = $this->project->url ?? throw new Exception("Project has no bundle URL");
        $envURL = $bundleURL->appendingPathComponent(".env");
        if (!FileManager::default()->fileExists($envURL->path)) {
            new DotEnvFileWriter($envURL, false, false)->save();
        }
    }

    /**
     * @throws Exception
     */
    private function updateDelegateIfNeeded(): void
    {
        $bundleURL = $this->project->url ?? throw new Exception("Project has no bundle URL");
        $fileManager = FileManager::default();
        $sourcesURL = $bundleURL->appendingPathComponent("src");
        $delegateURL = $sourcesURL->appendingPathComponent("Delegate")->appendingPathExtension("php");
        if ($fileManager->fileExists($delegateURL->path)) {
            return;
        }
        if (!$fileManager->fileExists($sourcesURL->path)) {
            $fileManager->createDirectory($sourcesURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
        }
        new DelegateFileWriter($delegateURL, false)->save();
    }
}
