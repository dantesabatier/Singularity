<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Bundles\BundleUpdater;
use App\Bundles\SaveBundleTransaction;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Sabatier\Foundation\FileManager;

final class SaveBundleTransactionTest extends CoreDataTestCase
{
    /**
     * @throws Exception
     */
    public function testExecuteRunsTheUpdaterAndExportsTheModel(): void
    {
        $project = $this->makeProject();
        $bundleURL = $project->url ?? self::fail("Project has no url");
        $model = $project->model ?? self::fail("Project has no model");
        $this->makeEntity($model, "Widget");
        $this->context->save();
        new BundleScaffolder($bundleURL, $project, new BundleGenerationOptions())->scaffold();

        $momURL = $bundleURL->appendingPathComponent("Resources")->appendingPathComponent($bundleURL->lastPathComponent . ".mom");
        FileManager::default()->removeItem($momURL);
        self::assertFalse(FileManager::default()->fileExists($momURL->path), "guard: model file removed before save");

        new SaveBundleTransaction(new BundleUpdater($project))->execute();

        self::assertTrue(FileManager::default()->fileExists($momURL->path), "the save transaction re-exports the model");
    }
}
