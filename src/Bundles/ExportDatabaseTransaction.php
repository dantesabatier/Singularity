<?php

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Override;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\parse_env_file;
use const Sabatier\CoreData\SQLSchemaCredentialPassword;
use const Sabatier\CoreData\SQLSchemaCredentialUser;
use const Sabatier\CoreData\SQLSchemaCredentialUserDefault;
use const Sabatier\CoreData\SQLSchemaHost;
use const Sabatier\CoreData\SQLSchemaHostDefault;
use const Sabatier\CoreData\SQLSchemaName;

final class ExportDatabaseTransaction implements Transaction
{
    /** @var Dictionary<string, string> */
    public Dictionary $environment {
        get {
            if (isset($this->environment)) {
                return $this->environment;
            }
            if (($environmentPath = $this->project->url?->appendingPathComponent(".env")?->path) && FileManager::default()->fileExists($environmentPath)) {
                return $this->environment = Dictionary::dictionaryWithArray(parse_env_file($environmentPath));
            }
            return $this->environment = new Dictionary();
        }
    }
    public URL $fileURL {
        get {
            if (isset($this->fileURL)) {
                return $this->fileURL;
            }
            $name = $this->environment[SQLSchemaName] ?? $this->project->name;
            $date = new Date()->format("Y-m-d_H-i-s");
            return $this->fileURL = $this->destinationDirectory->appendingPathComponent("{$name}_$date.sql");
        }
    }

    public function __construct(private readonly Project $project, private readonly URL $destinationDirectory)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $environment = $this->environment;
        $command = $this->buildCommand($environment);
        $this->executeCommand($command);
    }

    private function buildCommand(Dictionary $environment): string
    {
        $name = $environment[SQLSchemaName] ?? $this->project->name;
        $user = $environment[SQLSchemaCredentialUser] ?? SQLSchemaCredentialUserDefault;
        $password = $environment[SQLSchemaCredentialPassword];
        $host = $environment[SQLSchemaHost] ?? SQLSchemaHostDefault;
        return sprintf("mariadb-dump --user=%s --password=%s --host=%s %s > %s", escapeshellarg($user), escapeshellarg($password), escapeshellarg($host), escapeshellarg($name), escapeshellarg($this->fileURL->path));
    }

    private function executeCommand(string $command): void
    {
        exec($command, $output, $result);
        $result === 0 ?: fatal_error("Database export failed: " . implode("\n", $output));
    }
}
