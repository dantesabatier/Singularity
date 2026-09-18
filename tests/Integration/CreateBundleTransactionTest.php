<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Bundles\CreateBundleTransaction;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class CreateBundleTransactionTest extends CoreDataTestCase
{
    /**
     * The transaction creates the bundle directory itself, so the fixture hands it a location that
     * does not exist yet rather than the one `makeProject()` has already created.
     * @throws Exception
     */
    private function projectAtUnwrittenURL(URL $bundleURL): Project
    {
        $project = $this->makeProject();
        $project->url = $bundleURL;
        $this->context->save();
        return $project;
    }

    /**
     * @throws Exception
     */
    public function testExecuteScaffoldsTheBundle(): void
    {
        $bundleURL = $this->temporaryURL("MyApp", "sabatier");
        $project = $this->projectAtUnwrittenURL($bundleURL);
        $scaffolder = new BundleScaffolder($bundleURL, $project, new BundleGenerationOptions());

        new CreateBundleTransaction($scaffolder, $bundleURL)->execute();

        $fileManager = FileManager::default();
        self::assertTrue($fileManager->fileExists($bundleURL->appendingPathComponent("Info.plist")->path));
        self::assertTrue($fileManager->fileExists($bundleURL->appendingPathComponent("composer.json")->path));
    }

    /**
     * Planting a regular file where the bundle directory should go makes createDirectory fail, so
     * scaffold() throws and the transaction's rollback runs before rethrowing.
     * @throws Exception
     */
    public function testAFailedScaffoldRollsBackAndRethrows(): void
    {
        $bundleURL = $this->temporaryURL("MyApp", "sabatier");
        $fileManager = FileManager::default();
        $fileManager->createFile($bundleURL->path, "not a directory\n");

        $project = $this->projectAtUnwrittenURL($bundleURL);
        $scaffolder = new BundleScaffolder($bundleURL, $project, new BundleGenerationOptions());

        $threw = false;
        try {
            new CreateBundleTransaction($scaffolder, $bundleURL)->execute();
        } catch (Exception) {
            $threw = true;
        }

        self::assertTrue($threw, "execute rethrows the scaffolding failure");
        self::assertFalse($fileManager->fileExists($bundleURL->path, $isDirectory) && $isDirectory, "rollback did not leave a bundle directory behind");
    }
}
