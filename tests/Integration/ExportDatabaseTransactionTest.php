<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bundles\DatabaseExportationOptions;
use App\Bundles\ExportDatabaseTransaction;
use App\Tests\Support\CoreDataTestCase;
use Exception;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class ExportDatabaseTransactionTest extends CoreDataTestCase
{
    private const string PROJECT_NAME = "Bookstore";

    private URL $bundleURL;
    private URL $destination;

    /**
     * @throws Exception
     */
    private function transaction(): ExportDatabaseTransaction
    {
        $project = $this->makeProject(self::PROJECT_NAME);
        $this->bundleURL = $project->url ?? self::fail("Project has no url");
        return new ExportDatabaseTransaction($project, $this->destination, new DatabaseExportationOptions());
    }

    /**
     * The transaction reads the bundle's `.env` when it is built, so the file is written against the
     * project's own directory before the transaction is constructed.
     * @throws Exception
     */
    private function transactionWithEnvironment(string $contents): ExportDatabaseTransaction
    {
        $project = $this->makeProject(self::PROJECT_NAME);
        $this->bundleURL = $project->url ?? self::fail("Project has no url");
        FileManager::default()->createFile($this->bundleURL->appendingPathComponent(".env")->path, $contents);
        return new ExportDatabaseTransaction($project, $this->destination, new DatabaseExportationOptions());
    }

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->destination = $this->makeTemporaryDirectory("destination");
    }

    /**
     * @throws Exception
     */
    public function testEnvironmentIsEmptyWhenTheProjectHasNoEnvFile(): void
    {
        self::assertTrue($this->transaction()->environment->isEmpty);
    }

    /**
     * @throws Exception
     */
    public function testEnvironmentIsParsedFromTheProjectEnvFile(): void
    {
        $environment = $this->transactionWithEnvironment("SQL_SCHEMA_NAME=custom_db\nSQL_SCHEMA_HOST=db.example\n")->environment;
        self::assertSame("custom_db", $environment["SQL_SCHEMA_NAME"]);
        self::assertSame("db.example", $environment["SQL_SCHEMA_HOST"]);
    }

    /**
     * @throws Exception
     */
    public function testTheDumpFileNameUsesTheSchemaNameFromTheEnv(): void
    {
        $fileURL = $this->transactionWithEnvironment("SQL_SCHEMA_NAME=custom_db\n")->fileURL;
        self::assertStringStartsWith("custom_db_", $fileURL->lastPathComponent);
        self::assertStringEndsWith(".sql", $fileURL->lastPathComponent);
        self::assertStringStartsWith($this->destination->absoluteString, $fileURL->absoluteString, "the dump lands in the destination directory");
    }

    /**
     * @throws Exception
     */
    public function testTheDumpFileNameFallsBackToTheProjectNameWithoutAnEnv(): void
    {
        $fileURL = $this->transaction()->fileURL;
        self::assertStringStartsWith(self::PROJECT_NAME . "_", $fileURL->lastPathComponent);
    }
}
