<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleUpdater;
use App\Bundles\ModelBundle;
use App\Bundles\NewModelVersionTransaction;
use App\FileWriters\ModelFileWriter;
use App\Model\Model;
use App\Model\ModelMap;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class BundleUpdaterTest extends CoreDataTestCase
{
    private Project $project;
    private Model $model;
    private URL $bundleURL;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->model = $this->project->model ?? self::fail("Project has no model");
        // The updater names the model package after the bundle directory while the version transaction
        // names it after the project, so a real bundle directory carries the project's own name.
        $this->bundleURL = $this->makeTemporaryDirectory("bundle")->appendingPathComponent($this->project->name);
        FileManager::default()->createDirectory($this->bundleURL, true);
        $this->project->url = $this->bundleURL;
        $this->makeEntity($this->model, "Book");
        $this->context->save();
        FileManager::default()->createDirectory($this->modelBundle()->modelFileURL->deletingLastPathComponent(), true);
    }

    /**
     * @throws Exception
     */
    private function modelBundle(): ModelBundle
    {
        return new ModelBundle($this->bundleURL, $this->bundleURL->lastPathComponent);
    }

    /**
     * @throws Exception
     */
    private function update(): void
    {
        new BundleUpdater($this->project)->update();
    }

    /**
     * @throws Exception
     */
    public function testUpdatingWritesTheModelWhereTheGeneratedProjectLoadsItFrom(): void
    {
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->modelBundle()->currentVersionURL->path));
    }

    /**
     * @throws Exception
     */
    public function testAnUnversionedBundleGetsNoVersionInformationAndNoCachedModel(): void
    {
        $this->update();
        $modelBundle = $this->modelBundle();
        self::assertFalse($modelBundle->isVersioned);
        self::assertFalse(FileManager::default()->fileExists($modelBundle->versionInfoURL->path));
    }

    /**
     * @throws Exception
     */
    public function testAVersionedBundleRecordsTheChecksumOfTheVersionBeingEdited(): void
    {
        new NewModelVersionTransaction($this->project)->execute();
        $this->update();
        $modelBundle = $this->modelBundle();
        self::assertTrue($modelBundle->isVersioned);
        self::assertSame($this->model->managedObjectModel->versionChecksum, $modelBundle->versionChecksums[$modelBundle->currentVersionName]);
    }

    /**
     * @throws Exception
     */
    public function testAVersionedBundleKeepsTheFrozenVersionUntouchedWhileTheWorkInProgressIsRewritten(): void
    {
        $transaction = new NewModelVersionTransaction($this->project);
        $transaction->execute();
        $modelBundle = $this->modelBundle();
        $frozenURL = $modelBundle->urlForVersionNamed($transaction->frozenVersionName);
        $frozen = FileManager::default()->contents($frozenURL->path);
        $this->makeEntity($this->model, "Author");
        $this->context->save();
        $this->update();
        self::assertSame($frozen, FileManager::default()->contents($frozenURL->path));
        self::assertNotSame($frozen, FileManager::default()->contents($modelBundle->currentVersionURL->path));
    }

    /**
     * @throws Exception
     */
    public function testAMapThatNamesTheVersionItStartsFromIsWrittenAsAMappingModel(): void
    {
        $modelMap = $this->makeModelMap("BookstoreToBookstore 2");
        $modelMap->sourceModelURL = $this->writeSourceModel();
        $this->context->save();
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->modelBundle()->urlForMappingModelNamed($modelMap->name)->path));
    }

    /**
     * @throws Exception
     */
    public function testAMapWithoutASourceIsNotWrittenBecauseThereIsNoPairToMigrateBetween(): void
    {
        $modelMap = $this->makeModelMap("BookstoreToBookstore 2");
        $this->context->save();
        $this->update();
        self::assertFalse(FileManager::default()->fileExists($this->modelBundle()->urlForMappingModelNamed($modelMap->name)->path));
    }

    /**
     * @throws Exception
     */
    public function testTheEnvironmentFileIsScaffoldedWhenTheBundleHasNone(): void
    {
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->bundleURL->appendingPathComponent(".env")->path));
    }

    /**
     * @throws Exception
     */
    public function testAnEnvironmentFileTheDeveloperAlreadyTunedIsLeftAlone(): void
    {
        $url = $this->bundleURL->appendingPathComponent(".env");
        FileManager::default()->createFile($url->path, "APP_SECRET=kept\n");
        $this->update();
        self::assertSame("APP_SECRET=kept\n", FileManager::default()->contents($url->path));
    }

    /**
     * @throws Exception
     */
    public function testTheDelegateIsScaffoldedWhenTheBundleHasNone(): void
    {
        FileManager::default()->createDirectory($this->bundleURL->appendingPathComponent("src"), true);
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->delegateURL()->path));
    }

    /**
     * A project opened rather than created never went through the scaffolder, so its sources
     * directory may be missing and the delegate has nowhere to be written.
     * @throws Exception
     */
    public function testTheSourcesDirectoryIsScaffoldedAlongsideTheDelegateWhenItIsMissing(): void
    {
        self::assertFalse(FileManager::default()->fileExists($this->bundleURL->appendingPathComponent("src")->path));
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->delegateURL()->path));
    }

    /**
     * @throws Exception
     */
    public function testADelegateTheDeveloperAlreadyWroteIsLeftAlone(): void
    {
        $url = $this->delegateURL();
        FileManager::default()->createDirectory($url->deletingLastPathComponent(), true);
        FileManager::default()->createFile($url->path, "<?php // hand written\n");
        $this->update();
        self::assertSame("<?php // hand written\n", FileManager::default()->contents($url->path));
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutAModelSkipsTheModelButStillScaffoldsTheRest(): void
    {
        $this->context->delete($this->model);
        $this->context->save();
        $this->update();
        self::assertTrue(FileManager::default()->fileExists($this->bundleURL->appendingPathComponent(".env")->path));
    }

    private function delegateURL(): URL
    {
        return $this->bundleURL->appendingPathComponent("src")->appendingPathComponent("Delegate")->appendingPathExtension("php");
    }

    /**
     * @throws Exception
     */
    private function makeModelMap(string $name): ModelMap
    {
        $modelMap = new ModelMap($this->context);
        $modelMap->name = $name;
        $this->project->addModelMapsObject($modelMap);
        return $modelMap;
    }

    /**
     * @throws Exception
     */
    private function writeSourceModel(): URL
    {
        $url = $this->temporaryURL("source", "mom");
        new ModelFileWriter($url, $this->model)->save();
        return $url;
    }
}
