<?php

namespace App\Bundles;

use App\Model\Project;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\kCFBundleNameKey;

final readonly class ProjectBundleLoader
{
    public function __construct(private URL $url, private ManagedObjectContext $context)
    {
    }

    public function load(): Project
    {
        $this->assertBundleIntegrity();
        $project = new Project($this->context);
        $project->url = $this->url;
        $project->name = $this->readBundleName();
        return $project;
    }

    private function readBundleName(): string
    {
        return Bundle::bundleWithURL($this->url)->object(kCFBundleNameKey);
    }

    private function assertBundleIntegrity(): void
    {
        $this->assertBundleExists();
        $this->assertInfoPlistExists();
        $this->assertModelExists($this->readBundleName());
    }

    private function assertBundleExists(): void
    {
        if (!FileManager::default()->fileExists($this->url->path)) {
            fatal_error("Bundle does not exist");
        }
    }

    private function assertInfoPlistExists(): void
    {
        $infoURL = $this->url->appendingPathComponent("Info")->appendingPathExtension("plist");
        if (!FileManager::default()->fileExists($infoURL->path)) {
            fatal_error("Missing Info.plist");
        }
    }

    private function assertModelExists(string $bundleName): void
    {
        $resourcesURL = $this->url->appendingPathComponent("Resources");
        if (!FileManager::default()->fileExists($resourcesURL->path)) {
            fatal_error("Missing Resources directory");
        }
        $modelURL = $resourcesURL->appendingPathComponent($bundleName)->appendingPathExtension("mom");
        if (!FileManager::default()->fileExists($modelURL->path)) {
            fatal_error("Missing model file $bundleName.mom");
        }
    }
}

