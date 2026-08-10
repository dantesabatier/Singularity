<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\DelegateFileWriter;
use App\FileWriters\DotEnvFileWriter;
use App\FileWriters\ModelFileWriter;
use App\Model\Project;
use Exception;
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
        $this->updateEnvIfNeeded();
        $this->updateDelegateIfNeeded();
    }

    /**
     * @throws Exception
     */
    private function updateModelIfNeeded(): void
    {
        $model = $this->project->model;
        if ($model) {
            $bundleURL = $this->project->url ?? throw new Exception("Project has no bundle URL");
            $modelURL = $bundleURL->appendingPathComponent("Resources")->appendingPathComponent($bundleURL->lastPathComponent)->appendingPathExtension("mom");
            new ModelFileWriter($modelURL, $model)->save();
        }
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
        $delegateURL = $bundleURL->appendingPathComponent("src")->appendingPathComponent("Delegate")->appendingPathExtension("php");
        if (!FileManager::default()->fileExists($delegateURL->path)) {
            new DelegateFileWriter($delegateURL, false)->save();
        }
    }
}
