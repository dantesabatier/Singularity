<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\ModelBundle;
use App\Bundles\NewModelVersionTransaction;
use App\Model\Project;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Sabatier\Foundation\FileManager;

final class NewModelVersionTransactionTest extends CoreDataTestCase
{
    /**
     * @throws Exception
     */
    private function modelBundle(Project $project): ModelBundle
    {
        return new ModelBundle($project->url ?? self::fail("Project has no bundle URL"), $project->name);
    }

    /**
     * @throws Exception
     */
    private function makeVersionedProject(): Project
    {
        $project = $this->makeProject();
        FileManager::default()->createDirectory($this->modelBundle($project)->modelFileURL->deletingLastPathComponent(), true);
        return $project;
    }

    /**
     * @throws Exception
     */
    public function testFreezingTheFirstVersionTurnsTheLoneModelFileIntoAPackage(): void
    {
        $project = $this->makeVersionedProject();
        $modelBundle = $this->modelBundle($project);
        self::assertFalse($modelBundle->isVersioned);
        new NewModelVersionTransaction($project)->execute();
        self::assertTrue($modelBundle->isVersioned);
    }

    /**
     * @throws Exception
     */
    public function testTheFrozenVersionIsNumberedAndTheWorkInProgressKeepsTheBundleName(): void
    {
        $project = $this->makeVersionedProject();
        $transaction = new NewModelVersionTransaction($project);
        $transaction->execute();
        self::assertSame("Bookstore 2", $transaction->frozenVersionName);
        self::assertSame("Bookstore", $transaction->currentVersionName);
    }

    /**
     * @throws Exception
     */
    public function testBothTheFrozenVersionAndTheWorkInProgressExistOnDiskRightAway(): void
    {
        $project = $this->makeVersionedProject();
        $transaction = new NewModelVersionTransaction($project);
        $transaction->execute();
        $modelBundle = $this->modelBundle($project);
        $fileManager = FileManager::default();
        self::assertTrue($fileManager->fileExists($modelBundle->urlForVersionNamed($transaction->frozenVersionName)->path));
        self::assertTrue($fileManager->fileExists($modelBundle->urlForVersionNamed($transaction->currentVersionName)->path));
        self::assertTrue($fileManager->fileExists($modelBundle->currentVersionURL->path));
    }

    /**
     * @throws Exception
     */
    public function testTheLoneModelFileIsCarriedInsideThePackageUnderTheNameItAlreadyHad(): void
    {
        $project = $this->makeVersionedProject();
        $modelBundle = $this->modelBundle($project);
        file_put_contents($modelBundle->modelFileURL->path, "the model as it stood");
        new NewModelVersionTransaction($project)->execute();
        self::assertFalse(FileManager::default()->fileExists($modelBundle->modelFileURL->path));
        self::assertTrue(FileManager::default()->fileExists($modelBundle->urlForVersionNamed("Bookstore")->path));
    }

    /**
     * @throws Exception
     */
    public function testTheVersionInformationNamesTheVersionOpenForEditing(): void
    {
        $project = $this->makeVersionedProject();
        new NewModelVersionTransaction($project)->execute();
        $modelBundle = $this->modelBundle($project);
        self::assertSame("Bookstore", $modelBundle->currentVersionName);
        self::assertSame($modelBundle->urlForVersionNamed("Bookstore")->path, $modelBundle->currentVersionURL->path);
    }

    /**
     * @throws Exception
     */
    public function testEveryVersionIsRecordedWithItsChecksum(): void
    {
        $project = $this->makeVersionedProject();
        new NewModelVersionTransaction($project)->execute();
        $versionChecksums = $this->modelBundle($project)->versionChecksums;
        self::assertNotNull($versionChecksums->valueForKey("Bookstore"));
        self::assertNotNull($versionChecksums->valueForKey("Bookstore 2"));
    }

    /**
     * @throws Exception
     */
    public function testFreezingTwiceNumbersTheSecondVersionWithoutOverwritingTheFirst(): void
    {
        $project = $this->makeVersionedProject();
        $first = new NewModelVersionTransaction($project);
        $first->execute();
        $second = new NewModelVersionTransaction($project);
        $second->execute();
        self::assertSame("Bookstore 2", $first->frozenVersionName);
        self::assertSame("Bookstore 3", $second->frozenVersionName);
        $modelBundle = $this->modelBundle($project);
        $fileManager = FileManager::default();
        self::assertTrue($fileManager->fileExists($modelBundle->urlForVersionNamed("Bookstore 2")->path));
        self::assertTrue($fileManager->fileExists($modelBundle->urlForVersionNamed("Bookstore 3")->path));
        self::assertSame("Bookstore", $modelBundle->currentVersionName);
    }
}
