<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\FileWriters\HtaccessFileWriter;
use App\Tests\Support\TemporaryDirectoryTestCase;
use Exception;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class HtaccessFileWriterTest extends TemporaryDirectoryTestCase
{
    private const string bundleName = "Bookstore";

    private URL $bundleURL;

    /**
     * @throws Exception
     */
    private function makeWriter(): HtaccessFileWriter
    {
        $this->bundleURL = $this->makeTemporaryDirectory(self::bundleName);
        return new HtaccessFileWriter($this->bundleURL->appendingPathComponent(".htaccess"));
    }

    /**
     * @throws Exception
     */
    public function testTheConfigurationSitsAtTheBundleRootWhereApacheReadsIt(): void
    {
        $writer = $this->makeWriter();
        self::assertSame($this->bundleURL->path . "/.htaccess", $writer->url->path);
        $writer->save();
        self::assertTrue(FileManager::default()->fileExists($writer->url->path));
    }

    /**
     * @throws Exception
     */
    public function testEveryRequestThatIsNotIndexIsRewrittenFromTheDocumentRoot(): void
    {
        $contents = $this->makeWriter()->contents;
        self::assertStringContainsString("RewriteEngine on", $contents);
        self::assertStringContainsString("RewriteCond %{REQUEST_URI} !index.php", $contents);
        self::assertStringContainsString("RewriteRule ^(.*)$ /index.php?url=\$1 [L]", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheAuthorizationHeaderApacheStripsIsPutBackForPHP(): void
    {
        self::assertStringContainsString("SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1", $this->makeWriter()->contents);
    }

    /**
     * @throws Exception
     */
    public function testErrorsAreLoggedRatherThanDisplayed(): void
    {
        $contents = $this->makeWriter()->contents;
        self::assertStringContainsString("php_flag display_errors off", $contents);
        self::assertStringContainsString("php_flag display_startup_errors off", $contents);
        self::assertStringContainsString("php_flag log_errors on", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheErrorLogPointsInsideTheGeneratedProjectAndItsDirectoryIsCreated(): void
    {
        $writer = $this->makeWriter();
        $logDirectoryURL = $this->bundleURL->appendingPathComponent("Library")->appendingPathComponent("Logs");
        self::assertFalse(FileManager::default()->fileExists($logDirectoryURL->path));
        $writer->save();
        self::assertTrue(FileManager::default()->fileExists($logDirectoryURL->path, $isDirectory));
        self::assertTrue($isDirectory);
        self::assertStringContainsString("php_value error_log " . $logDirectoryURL->path . "/errors.log", $writer->contents);
    }

    /**
     * @throws Exception
     */
    public function testTheGeneratedFileIsNamedAfterTheBundleItConfigures(): void
    {
        $writer = $this->makeWriter();
        self::assertStringContainsString("# " . $this->bundleURL->lastPathComponent . " — Apache configuration", $writer->contents);
    }
}
