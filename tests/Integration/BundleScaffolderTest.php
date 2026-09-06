<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\BundleGenerationOptions;
use App\Bundles\BundleScaffolder;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class BundleScaffolderTest extends CoreDataTestCase
{
    private Project $project;
    private URL $bundleURL;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->bundleURL = $this->project->url ?? self::fail("Project has no url");
    }

    /**
     * @throws Exception
     */
    private function scaffold(): void
    {
        new BundleScaffolder($this->bundleURL, $this->project, new BundleGenerationOptions())->scaffold();
    }

    /**
     * @throws Exception
     */
    public function testAScaffoldedBundleCarriesTheApacheConfigurationApacheNeedsToRouteIt(): void
    {
        $this->scaffold();
        $url = $this->bundleURL->appendingPathComponent(".htaccess");
        self::assertTrue(FileManager::default()->fileExists($url->path));
        $contents = FileManager::default()->contents($url->path) ?? self::fail("Configuration is unreadable");
        self::assertStringContainsString("RewriteRule ^(.*)$ /index.php?url=\$1 [L]", $contents);
        self::assertStringContainsString("SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheDirectoryTheApacheConfigurationLogsIntoIsScaffoldedAlongsideIt(): void
    {
        $this->scaffold();
        self::assertTrue(FileManager::default()->fileExists($this->bundleURL->appendingPathComponent("Library")->appendingPathComponent("Logs")->path, $isDirectory));
        self::assertTrue($isDirectory);
    }

    /**
     * @throws Exception
     */
    public function testAnApacheConfigurationTheDeveloperAlreadyTunedSurvivesScaffolding(): void
    {
        $url = $this->bundleURL->appendingPathComponent(".htaccess");
        FileManager::default()->createFile($url->path, "# hand tuned\n");
        $this->scaffold();
        self::assertSame("# hand tuned\n", FileManager::default()->contents($url->path));
    }
}
