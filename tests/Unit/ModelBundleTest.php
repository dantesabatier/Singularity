<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Bundles\ModelBundle;
use App\Tests\Support\TemporaryDirectoryTestCase;
use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use const Sabatier\CoreData\ManagedObjectModelCurrentVersionNameKey;

final class ModelBundleTest extends TemporaryDirectoryTestCase
{
    private const string bundleName = "Bookstore";

    private URL $bundleURL;

    /**
     * @throws Exception
     */
    private function makeBundle(): ModelBundle
    {
        $this->bundleURL = $this->makeTemporaryDirectory(self::bundleName);
        FileManager::default()->createDirectory($this->bundleURL->appendingPathComponent("Resources"), true);
        return new ModelBundle($this->bundleURL, self::bundleName);
    }

    /**
     * @throws Exception
     */
    private function makePackagedBundle(): ModelBundle
    {
        $modelBundle = $this->makeBundle();
        FileManager::default()->createDirectory($modelBundle->packageURL, true);
        return $modelBundle;
    }

    /**
     * @throws Exception
     */
    public function testTheLoneModelFileAndThePackageSitSideBySideInResources(): void
    {
        $modelBundle = $this->makeBundle();
        $resourcesPath = $this->bundleURL->appendingPathComponent("Resources")->path;
        self::assertSame($resourcesPath . "/Bookstore.mom", $modelBundle->modelFileURL->path);
        self::assertSame($resourcesPath . "/Bookstore.momd", $modelBundle->packageURL->path);
    }

    /**
     * @throws Exception
     */
    public function testAProjectIsVersionedOnlyOnceThePackageExists(): void
    {
        $modelBundle = $this->makeBundle();
        self::assertFalse($modelBundle->isVersioned);
        FileManager::default()->createDirectory($modelBundle->packageURL, true);
        self::assertTrue($modelBundle->isVersioned);
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutAPackageLoadsAndWritesTheLoneModelFile(): void
    {
        $modelBundle = $this->makeBundle();
        self::assertSame($modelBundle->modelFileURL->path, $modelBundle->url->path);
        self::assertSame($modelBundle->modelFileURL->path, $modelBundle->currentVersionURL->path);
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithAPackageLoadsThePackageAndWritesItsCurrentVersion(): void
    {
        $modelBundle = $this->makePackagedBundle();
        $modelBundle->writeVersionInfo("Bookstore 3", new Dictionary(["Bookstore 3" => "hash"]));
        self::assertSame($modelBundle->packageURL->path, $modelBundle->url->path);
        self::assertSame($modelBundle->packageURL->path . "/Bookstore 3.mom", $modelBundle->currentVersionURL->path);
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutAPackageHasOneVersionNamedAfterTheBundle(): void
    {
        self::assertSame(self::bundleName, $this->makeBundle()->currentVersionName);
    }

    /**
     * @throws Exception
     */
    public function testAPackageWithoutVersionInformationFallsBackToTheBundleName(): void
    {
        self::assertSame(self::bundleName, $this->makePackagedBundle()->currentVersionName);
    }

    /**
     * @throws Exception
     */
    public function testAPackageNamesTheVersionItsVersionInformationRecords(): void
    {
        $modelBundle = $this->makePackagedBundle();
        $modelBundle->writeVersionInfo("Bookstore 2", new Dictionary(["Bookstore 2" => "hash"]));
        self::assertSame("Bookstore 2", $modelBundle->currentVersionName);
    }

    /**
     * @throws Exception
     */
    public function testAVersionIsAModelFileInsideThePackage(): void
    {
        $modelBundle = $this->makeBundle();
        self::assertSame($modelBundle->packageURL->path . "/Bookstore 2.mom", $modelBundle->urlForVersionNamed("Bookstore 2")->path);
    }

    /**
     * @throws Exception
     */
    public function testAMappingModelSitsBesideThePackageSoTheBundleCanEnumerateIt(): void
    {
        $modelBundle = $this->makeBundle();
        $mappingModelURL = $modelBundle->urlForMappingModelNamed("BookstoreToBookstore 2");
        self::assertSame($this->bundleURL->appendingPathComponent("Resources")->path . "/BookstoreToBookstore 2.cdm", $mappingModelURL->path);
        self::assertStringStartsNotWith($modelBundle->packageURL->path, $mappingModelURL->path);
    }

    /**
     * @throws Exception
     */
    public function testWrittenVersionInformationReadsBackAsTheCurrentVersionAndItsChecksums(): void
    {
        $modelBundle = $this->makePackagedBundle();
        /** @var Dictionary<string> $versionChecksums */
        $versionChecksums = new Dictionary(["Bookstore" => "second", "Bookstore 2" => "first"]);
        $modelBundle->writeVersionInfo("Bookstore", $versionChecksums);
        self::assertSame("Bookstore", $modelBundle->versionInfo->valueForKey(ManagedObjectModelCurrentVersionNameKey));
        self::assertSame("Bookstore", $modelBundle->currentVersionName);
        self::assertSame("first", $modelBundle->versionChecksums->valueForKey("Bookstore 2"));
        self::assertSame("second", $modelBundle->versionChecksums->valueForKey("Bookstore"));
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutAPackageCarriesNoVersionInformation(): void
    {
        $modelBundle = $this->makeBundle();
        self::assertSame(0, $modelBundle->versionInfo->count);
        self::assertSame(0, $modelBundle->versionChecksums->count);
    }
}
