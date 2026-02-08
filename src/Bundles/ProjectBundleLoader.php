<?php

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\kCFBundleNameKey;

final readonly class ProjectBundleLoader
{
    public function __construct(private URL $url, private ManagedObjectContext $context)
    {
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    private function assertBundleIntegrity(): void
    {
        $this->assertBundleExists();
        $this->assertInfoPlistExists();
        $this->assertModelExists();
    }

    /**
     * @throws Exception
     */
    private function assertBundleExists(): void
    {
        if (!FileManager::default()->fileExists($this->url->path)) {
            throw new Exception("Bundle does not exist");
        }
    }

    /**
     * @throws Exception
     */
    private function assertInfoPlistExists(): void
    {
        $infoURL = $this->url->appendingPathComponent("Info")->appendingPathExtension("plist");
        if (!FileManager::default()->fileExists($infoURL->path)) {
            throw new Exception("Missing Info.plist");
        }
    }

    /**
     * @throws Exception
     */
    private function assertModelExists(): void
    {
        $resourcesURL = $this->url->appendingPathComponent("Resources");
        if (!FileManager::default()->fileExists($resourcesURL->path)) {
            throw new Exception("Missing Resources directory");
        }
    }
}

