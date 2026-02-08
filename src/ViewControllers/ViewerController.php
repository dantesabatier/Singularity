<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace App\ViewControllers;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\Action;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONDecorator;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\parse_env_file;
use const App\EntityPositionsMappingPreferencesKey;
use const Sabatier\CoreData\SQLSchemaCredentialPassword;
use const Sabatier\CoreData\SQLSchemaCredentialUser;
use const Sabatier\CoreData\SQLSchemaCredentialUserDefault;
use const Sabatier\CoreData\SQLSchemaHost;
use const Sabatier\CoreData\SQLSchemaHostDefault;
use const Sabatier\CoreData\SQLSchemaName;

#[Endpoint("Viewer")]
final class ViewerController extends ProjectController
{
    public string $name = "Viewer";
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }

    #[Override]
    public function viewWillLoad(): void
    {
        $this->title = "SQL Schema";
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function export(): void
    {
        $body = $this->request->parsedBody;
        $directory = $body["directory"] ?? throw new BadRequestException();
        $project = $this->project;
        /** @var URL $projectURL */
        $projectURL = $project->url;
        $environmentURL = $projectURL->appendingPathComponent(".env");
        $environmentPath = $environmentURL->path;
        FileManager::default()->fileExists($environmentPath) ?: fatal_error("Unable to find the environment file");
        $environment = Dictionary::dictionaryWithArray(parse_env_file($environmentPath));
        $name = $environment[SQLSchemaName] ?? fatal_error("Unable to find the SQL schema name");
        $user = $environment[SQLSchemaCredentialUser] ?? SQLSchemaCredentialUserDefault;
        $password = $environment[SQLSchemaCredentialPassword];
        $host = $environment[SQLSchemaHost] ?? SQLSchemaHostDefault;
        $date = new Date()->format("Y-m-d_H-i-s");
        $fileURL = URL::fileURL($directory)->appendingPathComponent("backup_{$name}_$date.sql");
        $command = sprintf("mariadb-dump --user=%s --password=%s --host=%s %s > %s", escapeshellarg($user), escapeshellarg($password), escapeshellarg($host), escapeshellarg($name), escapeshellarg($fileURL->path));
        exec($command, $output, $result);
        $result === 0 ?: fatal_error("Database export failed: " . implode("\n", $output));
        $this->data = ["url" => $fileURL];
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function moved(): void
    {
        $name = $this->project->name;
        $body = $this->request->parsedBody;
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = UserDefaults::standard()->dictionary(EntityPositionsMappingPreferencesKey) ?? new Dictionary();
        /** @var Dictionary<mixed> $dictionary */
        $project = $dictionary[$name] ?? new Dictionary();
        $project[$body["name"]] = $body["pos"];
        $dictionary[$name] = $project;
        UserDefaults::standard()->setObject($dictionary, EntityPositionsMappingPreferencesKey);
        $this->data = [];
    }
}
