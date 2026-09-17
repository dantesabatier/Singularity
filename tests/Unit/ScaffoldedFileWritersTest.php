<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Delegate;
use App\FileWriters\CliFileWriter;
use App\FileWriters\ComposerJsonFileWriter;
use App\FileWriters\DelegateFileWriter;
use App\FileWriters\IndexFileWriter;
use App\FileWriters\PlistFileWriter;
use App\Tests\Support\TemporaryDirectoryTestCase;
use Exception;
use InvalidArgumentException;
use Override;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\CompanyNamePreferencesKey;
use const Sabatier\Foundation\kCFBundleIdentifierKey;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundlePrincipalClassKey;

/**
 * Covers the files a generated project is scaffolded with, other than the model and the `.env`.
 */
final class ScaffoldedFileWritersTest extends TemporaryDirectoryTestCase
{
    private URL $bundleURL;
    private ?string $companyName;

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->bundleURL = $this->makeTemporaryDirectory("Bookstore");
        $this->companyName = UserDefaults::standard()->string(CompanyNamePreferencesKey);
        UserDefaults::standard()->setObject("Sabatier Software", CompanyNamePreferencesKey);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->companyName === null ? UserDefaults::standard()->removeObject(CompanyNamePreferencesKey) : UserDefaults::standard()->setObject($this->companyName, CompanyNamePreferencesKey);
        parent::tearDown();
    }

    private function url(string $name, string $pathExtension): URL
    {
        return $this->bundleURL->appendingPathComponent($name)->appendingPathExtension($pathExtension);
    }

    /**
     * @throws Exception
     */
    public function testTheEntryPointBootsTheApplicationTheFrameworkRuns(): void
    {
        $contents = new IndexFileWriter($this->url("index", "php"))->contents;
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $contents);
        self::assertStringContainsString("require_once __DIR__ . \"/vendor/autoload.php\";", $contents);
        self::assertStringContainsString("use Sabatier\\Service\\Application;", $contents);
        self::assertStringEndsWith("Application::shared()->run();\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testTheCommandLineEntryPointRunsTheJobRunnerInstead(): void
    {
        $contents = new CliFileWriter($this->url("cli", "php"))->contents;
        self::assertStringContainsString("use Sabatier\\Service\\Jobs\\JobRunner;", $contents);
        self::assertStringEndsWith("new JobRunner()->run();\n", $contents);
    }

    /**
     * @throws Exception
     */
    public function testThePackageIsNamedAfterTheCompanyAndTheBundle(): void
    {
        self::assertSame("sabatier-software/" . $this->slug(), $this->composer()["name"]);
    }

    /**
     * @throws Exception
     */
    public function testThePackageRequiresTheThreeStackPackagesTheGeneratedProjectRunsOn(): void
    {
        $require = $this->composer()["require"];
        foreach (["sabatier/foundation", "sabatier/coredata", "sabatier/service"] as $package) {
            self::assertArrayHasKey($package, $require);
        }
    }

    /**
     * @throws Exception
     */
    public function testThePackageResolvesTheStackFromTheSiblingCheckoutsItIsBuiltAgainst(): void
    {
        $urls = array_map(fn(array $repository): string => $repository["url"], $this->composer()["repositories"]);
        self::assertSame(["../Sabatier/Foundation", "../Sabatier/CoreData", "../Sabatier/Service"], $urls);
    }

    /**
     * @throws Exception
     */
    public function testThePackageAutoloadsTheGeneratedSourcesUnderTheAppNamespace(): void
    {
        self::assertSame(["App\\" => "src"], $this->composer()["autoload"]["psr-4"]);
    }

    /**
     * @throws Exception
     */
    public function testACompanyNameThatSlugsToNothingIsRefusedRatherThanWrittenOut(): void
    {
        UserDefaults::standard()->setObject("///", CompanyNamePreferencesKey);
        $this->expectException(InvalidArgumentException::class);
        new ComposerJsonFileWriter($this->url("composer", "json"))->contents;
    }

    /**
     * @throws Exception
     */
    public function testTheBundleInformationNamesTheBundleAndItsPrincipalClass(): void
    {
        $info = $this->plist();
        self::assertSame($this->bundleURL->lastPathComponent, $info[kCFBundleNameKey]);
        self::assertSame(Delegate::class, $info[kCFBundlePrincipalClassKey]);
    }

    /**
     * @throws Exception
     */
    public function testTheBundleIdentifierIsBuiltFromTheCompanyAndTheBundle(): void
    {
        self::assertSame("com.sabatier-software." . $this->slug(), $this->plist()[kCFBundleIdentifierKey]);
    }

    /**
     * @throws Exception
     */
    public function testAnOpenProjectIsHandedTheAccessPolicyThatLetsEveryRequestThrough(): void
    {
        $contents = new DelegateFileWriter($this->url("Delegate", "php"), false)->contents;
        self::assertStringContainsString("namespace App;", $contents);
        self::assertStringContainsString("class Delegate extends ObjectClass implements ApplicationDelegate", $contents);
        self::assertStringContainsString("\$application->accessPolicy = new PublicAccessPolicy();", $contents);
    }

    /**
     * @throws Exception
     */
    public function testASecuredProjectIsLeftWithTheFrameworkDefaultRatherThanTheOpenPolicy(): void
    {
        self::assertStringNotContainsString("\$application->accessPolicy = new PublicAccessPolicy();", new DelegateFileWriter($this->url("Delegate", "php"), true)->contents);
    }

    /**
     * @throws Exception
     */
    public function testTheDelegateAnswersEveryLifecycleCallbackTheFrameworkSends(): void
    {
        $contents = new DelegateFileWriter($this->url("Delegate", "php"), false)->contents;
        foreach (["public static function initialize(): void", "public function applicationWillFinishLaunching(Application \$application): void", "public function applicationDidFinishLaunching(Application \$application): void", "public function applicationWillTerminate(Application \$application): void", "public function applicationDidCrash(Application \$application, Throwable \$throwable): void"] as $signature) {
            self::assertStringContainsString($signature, $contents);
        }
    }

    /**
     * @throws Exception
     */
    public function testTheDelegateIsNamedAfterTheFileRatherThanItsDirectory(): void
    {
        self::assertSame("Delegate", new DelegateFileWriter($this->url("Delegate", "php"), false)->name);
    }

    /**
     * @throws Exception
     */
    public function testSavingPutsEachScaffoldedFileOnDiskWhereTheProjectExpectsIt(): void
    {
        $url = $this->url("index", "php");
        new IndexFileWriter($url)->save();
        self::assertTrue(FileManager::default()->fileExists($url->path));
    }

    private function slug(): string
    {
        return strtolower($this->bundleURL->lastPathComponent);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function composer(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(new ComposerJsonFileWriter($this->url("composer", "json"))->contents, true);
        return $decoded;
    }

    /**
     * @throws Exception
     */
    private function plist(): mixed
    {
        $url = $this->url("Info", "plist");
        new PlistFileWriter($url)->save();
        return PropertyListSerialization::propertyListWithURL($url);
    }
}
