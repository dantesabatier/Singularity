<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\FileWriters\DotEnvFileWriter;
use App\Tests\Support\TemporaryDirectoryTestCase;
use Exception;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

final class DotEnvFileWriterTest extends TemporaryDirectoryTestCase
{
    private const string bundleName = "Bookstore";

    /**
     * @throws Exception
     */
    private function write(bool $withCORS = false, bool $withJWT = false): string
    {
        $url = $this->bundleURL()->appendingPathComponent(".env");
        new DotEnvFileWriter($url, $withCORS, $withJWT)->save();
        return FileManager::default()->contents($url->path) ?? self::fail("Environment file is unreadable");
    }

    /**
     * @throws Exception
     */
    private function bundleURL(): URL
    {
        return $this->makeTemporaryDirectory(self::bundleName);
    }

    /**
     * @throws Exception
     */
    public function testTheStoreIsPointedAtASchemaNamedAfterTheBundleDirectory(): void
    {
        $bundleURL = $this->bundleURL();
        $url = $bundleURL->appendingPathComponent(".env");
        new DotEnvFileWriter($url, false, false)->save();
        $contents = FileManager::default()->contents($url->path) ?? self::fail("Environment file is unreadable");
        self::assertStringContainsString("SQL_SCHEMA_NAME=" . $bundleURL->lastPathComponent . "\n", $contents);
        self::assertStringContainsString("SQL_SCHEMA_HOST=127.0.0.1\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheCredentialsAreScaffoldedForTheDeveloperToFillIn(): void
    {
        $contents = $this->write();
        self::assertStringContainsString("SQL_SCHEMA_CREDENTIAL_USER=root\n", $contents);
        self::assertStringContainsString("SQL_SCHEMA_CREDENTIAL_PASSWORD=\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutCrossOriginAccessCarriesNoneOfItsConfiguration(): void
    {
        self::assertStringNotContainsString("CORS_", $this->write());
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithCrossOriginAccessCarriesTheOriginsMethodsAndHeadersItAdmits(): void
    {
        $contents = $this->write(withCORS: true);
        self::assertStringContainsString("CORS_ALLOWED_ORIGINS=http://localhost\n", $contents);
        self::assertStringContainsString("CORS_ALLOWED_METHODS=OPTIONS,HEAD,GET,POST,PATCH,DELETE\n", $contents);
        self::assertStringContainsString("CORS_ALLOWED_HEADERS=Content-Type,Authorization,Serialization\n", $contents);
        self::assertStringContainsString("CORS_ALLOW_CREDENTIALS=true\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithoutTokenAuthenticationCarriesNoSigningKey(): void
    {
        self::assertStringNotContainsString("JWT_", $this->write());
    }

    /**
     * @throws Exception
     */
    public function testAProjectWithTokenAuthenticationCarriesASigningKeyAndAValidityWindow(): void
    {
        $contents = $this->write(withJWT: true);
        self::assertMatchesRegularExpression("/JWT_PRIVATE_KEY=[A-Za-z0-9+\\/]+={0,2}\\n/", $contents);
        self::assertStringContainsString("JWT_VALIDITY_TIME_INTERVAL=28800\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheSigningKeyIsFreshForEveryProjectSoTwoNeverShareOne(): void
    {
        self::assertNotSame($this->signingKey($this->write(withJWT: true)), $this->signingKey($this->write(withJWT: true)));
    }

    /**
     * @throws Exception
     */
    public function testAProjectCanCarryBothCrossOriginAccessAndTokenAuthentication(): void
    {
        $contents = $this->write(withCORS: true, withJWT: true);
        self::assertStringContainsString("CORS_ALLOWED_ORIGINS=", $contents);
        self::assertStringContainsString("JWT_PRIVATE_KEY=", $contents);
    }

    /**
     * @throws Exception
     */
    public function testEveryEntryIsWrittenAsOneLineTheEnvironmentParserReads(): void
    {
        foreach (explode("\n", rtrim($this->write(withCORS: true, withJWT: true), "\n")) as $line) {
            self::assertMatchesRegularExpression("/^[A-Z_]+=/", $line);
        }
    }

    private function signingKey(string $contents): string
    {
        preg_match("/JWT_PRIVATE_KEY=(.*)/", $contents, $matches);
        return $matches[1] ?? self::fail("No signing key was written");
    }
}
