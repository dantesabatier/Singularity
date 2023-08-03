<?php

namespace App\ViewControllers;

use App\Delegate;
use App\Model\Model;
use App\Model\Project;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Sabatier\Service\Endpoint;
use Sabatier\Service\Outlet;
use Sabatier\Service\View;
use Sabatier\Service\ViewController;
use Throwable;
use const App\CompanyNameKey;
use const Sabatier\CoreData\SQLStoreType;
use const Sabatier\Foundation\kCFBundleDevelopmentRegionKey;
use const Sabatier\Foundation\kCFBundleDocumentTypesKey;
use const Sabatier\Foundation\kCFBundleExecutableKey;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleIdentifierKey;
use const Sabatier\Foundation\kCFBundleLocalizationsKey;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundlePackageTypeKey;
use const Sabatier\Foundation\kCFBundlePrincipalClassKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleTypeNameKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

#[Endpoint("/")]
class Welcome extends ViewController
{
    /** @var ArrayClass<Project> */
    #[Outlet]
    public readonly ArrayClass $projects;
    #[Outlet]
    public ?string $version = null;
    #[Outlet]
    public ?string $shortVersion = null;
    #[Outlet]
    public ?string $copyright = null;

    private function generateDelegateClass(string $class, string $namespace): string
    {
        $uses = new ArrayClass([
            "use " . HTTPURLResponse::class . ";",
            "use " . ObjectClass::class . ";",
            "use " . Application::class . ";",
            "use " . ApplicationDelegate::class . ";",
            "use " . View::class . ";",
            "use " . Throwable::class . ";",
        ]);
        $content = "<?php\n";
        $content .= "\n";
        if ($namespace) {
            $content .= "namespace $namespace;\n";
        }
        $content .= "\n";
        $content .= $uses->sort()->join("\n");
        $content .= "\n";
        $content .= "\n";
        $content .= "class $class extends ObjectClass implements ApplicationDelegate\n";
        $content .= "{\n";
        $content .= "    public static function initialize(): void\n";
        $content .= "    {\n";
        $content .= "    }\n";
        $content .= "\n";
        $content .= "    public function applicationWillFinishLaunching(Application \$application): void\n";
        $content .= "    {\n";
        $content .= "    }\n";
        $content .= "\n";
        $content .= "    public function applicationWillFail(Application \$application, HTTPURLResponse \$response, Throwable \$throwable): View|string|null\n";
        $content .= "    {\n";
        $content .= "        return null;\n";
        $content .= "    }\n";
        $content .= "\n";
        $content .= "    public function applicationWillTerminate(Application \$application): void\n";
        $content .= "    {\n";
        $content .= "    }\n";
        $content . "}\n";
        return $content;
    }

    private function generateIndex(): string
    {
        $content = "<?php\n";
        $content .= "\n";
        $content .= "require_once __DIR__ . \"/vendor/autoload.php\";\n";
        $content .= "\n";
        $content .= "use " . Application::class . ";\n";
        $content .= "\n";
        $content .= "Application::shared()->run();\n";
        return $content;
    }

    /**
     * @throws Exception
     */
    public function viewWillLoad(): void
    {
        $fetchRequest = Project::fetchRequest();
        $fetchRequest->propertiesToFetch = new ArrayClass(["name", "url"]);
        $fetchRequest->sortDescriptors = new ArrayClass([new SortDescriptor("creationDate")]);
        $this->projects = $this->managedObjectContext->fetch($fetchRequest);
        $this->version = $this->bundle->object(kCFBundleVersionKey);
        $this->shortVersion = $this->bundle->object(kCFBundleShortVersionStringKey);
        $this->copyright = $this->bundle->object(kCFBundleHumanReadableCopyright);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function create(): void
    {
        $body = $this->request->getParsedBody();
        /** @var string|null $path */
        $path = $body["location"] ?? null;
        if (!$path) {
            return;
        }
        $url = URL::fileURL($path);
        $fileManager = FileManager::default();
        if (!$fileManager->fileExists($url->path)) {
            $fileManager->createDirectory($url);
        }
        $name = $url->lastPathComponent;
        $lowerCaseName = strtolower($name);
        $path = $url->appendingPathComponent("Info")->appendingPathExtension("plist")->path;
        if (!$fileManager->fileExists($path)) {
            $fileManager->createFile($path, PropertyListSerialization::data(Dictionary::dictionaryWithArray([
                kCFBundleDevelopmentRegionKey => "English",
                kCFBundleExecutableKey => $name,
                kCFBundleIdentifierKey => sprintf("com.%s.%s", strtolower(str_replace(" ", "", (string)UserDefaults::standard()->string(CompanyNameKey))), $lowerCaseName),
                kCFBundleNameKey => $name,
                kCFBundleVersionKey => "1",
                kCFBundleShortVersionStringKey => "0.1",
                kCFBundlePackageTypeKey => "APPL",
                kCFBundlePrincipalClassKey => Delegate::class,
                kCFBundleLocalizationsKey => [
                    "en"
                ],
                kCFBundleDocumentTypesKey => [
                    [
                        kCFBundleTypeNameKey => SQLStoreType
                    ]
                ]
            ])));
        }
        $resourceURL = $url->appendingPathComponent("Resources");
        if (!$fileManager->fileExists($resourceURL->path)) {
            $fileManager->createDirectory($resourceURL);
        }
        $bundle = Bundle::bundleWithURL($url);
        foreach ($bundle->localizations as $localization) {
            $directoryURL = $resourceURL->appendingPathComponent($localization);
            if (!$fileManager->fileExists($directoryURL->path)) {
                $fileManager->createDirectory($directoryURL);
            }
        }
        /** @var class-string|null $principalClass */
        $principalClass = $bundle->object(kCFBundlePrincipalClassKey);
        if ($principalClass !== null) {
            $components = new ArrayClass(explode("\\", $principalClass));
            $class = $components->popLast() ?? Delegate::className();
            $namespace = $components->join("\\");
            $sourcesURL = $url->appendingPathComponent("src");
            if (!$fileManager->fileExists($sourcesURL->path)) {
                $fileManager->createDirectory($sourcesURL);
            }
            $path = $sourcesURL->appendingPathComponent($class)->appendingPathExtension("php")->path;
            if (!$fileManager->fileExists($path)) {
                $fileManager->createFile($path, $this->generateDelegateClass($class, $namespace));
            }
        }
        $storeURL = $resourceURL->appendingPathComponent($name)->appendingPathExtension("plist");
        if (!$fileManager->fileExists($storeURL->path)) {
            PropertyListSerialization::writePropertyList(Dictionary::dictionaryWithArray(["entities" => []]), $storeURL);
        }
        $path = $url->appendingPathComponent("composer")->appendingPathExtension("json")->path;
        if (!$fileManager->fileExists($path)) {
            $fileManager->createFile($path, json_encode([
                "name" => "vendor/$lowerCaseName",
                "description" => "description",
                "license" => "license",
                "keywords" => [
                    $lowerCaseName,
                ],
                "require" => [
                    "php" => sprintf("^%s", PHP_VERSION),
                    "ext-curl" => "*",
                    "ext-dom" => "*",
                    "ext-fileinfo" => "*",
                    "ext-gettext" => "*",
                    "ext-json" => "*",
                    "ext-mbstring" => "*",
                    "sabatier/foundation" => "^1.0-dev",
                    "sabatier/coredata" => "^1.0-dev",
                    "sabatier/service" => "^1.0-dev",
                ],
                "config" => [
                    "platform" => [
                        "ext-pcntl" => PHP_VERSION,
                        "ext-posix" => PHP_VERSION,
                        "ext-gd" => PHP_VERSION,
                        "ext-intl" => PHP_VERSION,
                    ]
                ],
                "autoload" => [
                    "psr-4" => [
                        "App\\" => "src"
                    ]
                ],
                "repositories" => array_map(fn(string $name): array => ["type" => "path", "url" => "../Sabatier/$name"], ["Foundation", "CoreData", "Service"])
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $path = $url->appendingPathComponent(".env")->path;
        if (!$fileManager->fileExists($path)) {
            $dictionary = new Dictionary([
                "SQL_SCHEMA_NAME" => $name,
                "SQL_SCHEMA_HOST" => "localhost",
                "SQL_SCHEMA_CREDENTIAL_USER" => "root",
                "SQL_SCHEMA_CREDENTIAL_PASSWORD" => ""
            ]);
            $fileManager->createFile($path, $dictionary->reduce("", fn(string &$result, string $value, string $key): string => $result .= "$key=$value\n"));
        }
        $path = $url->appendingPathComponent("index")->appendingPathExtension("php")->path;
        if (!$fileManager->fileExists($path)) {
            $fileManager->createFile($path, $this->generateIndex());
        }
        $context = $this->managedObjectContext;
        $model = new Model($context);
        $model->url = $bundle->url($name, "plist");
        $project = new Project($context);
        $project->name = $name;
        $project->url = $url;
        $project->model = $model;
        $context->save();
    }

    /**
     * @throws Exception
     */
    #[Action(HTTPRequestMethod::delete)]
    public function remove(): void
    {
    }
}
