<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Bundles\ProjectBundleLoader;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\URL;

final class ProjectBundleLoaderTest extends CoreDataTestCase
{
    private URL $bundleURL;

    /**
     * Scaffolds one well-formed bundle the whole class reads back, named after the project so the
     * model package resolves under the name the loader looks for.
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $project = $this->makeProject();
        $this->bundleURL = $this->makeTemporaryDirectory("bundle")->appendingPathComponent($project->name);
        FileManager::default()->createDirectory($this->bundleURL, true);
        $project->url = $this->bundleURL;
        $this->makeEntity($project->model ?? self::fail("Project has no model"), "Book");
        $this->context->save();
        new BundleScaffolder($this->bundleURL, $project, new BundleGenerationOptions())->scaffold();
    }

    /**
     * @throws Exception
     */
    private function load(): void
    {
        new ProjectBundleLoader($this->bundleURL, $this->context)->load();
    }

    /**
     * @throws Exception
     */
    public function testAWellFormedBundleLoadsAsAProjectPointingBackAtIt(): void
    {
        $project = new ProjectBundleLoader($this->bundleURL, $this->context)->load();
        self::assertSame($this->bundleURL->path, $project->url?->path);
    }

    /**
     * @throws Exception
     */
    public function testTheProjectTakesItsNameFromTheBundleRatherThanTheDirectory(): void
    {
        $project = new ProjectBundleLoader($this->bundleURL, $this->context)->load();
        self::assertSame("Bookstore", $project->name);
    }

    /**
     * @throws Exception
     */
    public function testTheLoadedProjectBelongsToTheContextItWasAskedFor(): void
    {
        $project = new ProjectBundleLoader($this->bundleURL, $this->context)->load();
        self::assertSame($this->context, $project->managedObjectContext);
    }

    public function testADirectoryThatIsNotThereIsRefused(): void
    {
        $this->bundleURL = $this->bundleURL->appendingPathComponent("nowhere");
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Bundle does not exist");
        $this->load();
    }

    /**
     * @throws Exception
     */
    public function testABundleWithoutItsInformationFileIsRefused(): void
    {
        FileManager::default()->removeItem($this->bundleURL->appendingPathComponent("Info")->appendingPathExtension("plist"));
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Missing Info.plist");
        $this->load();
    }

    /**
     * @throws Exception
     */
    public function testABundleWithoutItsResourcesDirectoryIsRefused(): void
    {
        $this->removeDirectory($this->bundleURL->appendingPathComponent("Resources"));
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Missing Resources directory");
        $this->load();
    }

    /**
     * @throws Exception
     */
    public function testABundleWhoseModelIsMissingIsRefusedByName(): void
    {
        FileManager::default()->removeItem($this->bundleURL->appendingPathComponent("Resources")->appendingPathComponent("Bookstore")->appendingPathExtension("mom"));
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Missing model Bookstore");
        $this->load();
    }

    /**
     * @throws Exception
     */
    private function removeDirectory(URL $url): void
    {
        $fileManager = FileManager::default();
        foreach ($fileManager->contentsOfDirectory($url) as $child) {
            $fileManager->removeItem($child);
        }
        $fileManager->removeItem($url);
    }
}
