<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

abstract class TemporaryDirectoryTestCase extends TestCase
{
    private URL $temporaryDirectoryURL;
    private int $temporaryNameCounter = 0;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        $this->temporaryDirectoryURL = URL::fileURL(sys_get_temp_dir() . "/singularity-tests-" . bin2hex(random_bytes(8)));
        FileManager::default()->createDirectory($this->temporaryDirectoryURL, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeItem($this->temporaryDirectoryURL);
    }

    /**
     * Returns the location of a file inside this test's temporary directory, whether or not it exists.
     */
    protected function temporaryURL(string $name, ?string $pathExtension = null): URL
    {
        $url = $this->temporaryDirectoryURL->appendingPathComponent($name . "-" . $this->temporaryNameCounter++);
        return $pathExtension === null ? $url : $url->appendingPathExtension($pathExtension);
    }

    /**
     * Returns a directory inside this test's temporary directory, created and empty.
     * @throws Exception
     */
    protected function makeTemporaryDirectory(string $name = "directory"): URL
    {
        $url = $this->temporaryURL($name);
        FileManager::default()->createDirectory($url, true);
        return $url;
    }

    private function removeItem(URL $url): void
    {
        $fileManager = FileManager::default();
        if (!$fileManager->fileExists($url->path, $isDirectory)) {
            return;
        }
        if ($isDirectory) {
            foreach ($fileManager->contentsOfDirectory($url) as $child) {
                $this->removeItem($child);
            }
        }
        $fileManager->removeItem($url);
    }
}
