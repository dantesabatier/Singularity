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
    /** @var Dictionary<string> */
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
    private string $database {
        get => $this->database ??= $this->environment[SQLSchemaName] ?? $this->project->name;
    }
    private string $user {
        get => $this->user ??= $this->environment[SQLSchemaCredentialUser] ?? SQLSchemaCredentialUserDefault;
    }
    private string $password {
        get => $this->password ??= $this->environment[SQLSchemaCredentialPassword] ?? "";
    }
    private string $host {
        get => $this->host ??= $this->environment[SQLSchemaHost] ?? SQLSchemaHostDefault;
    }
    private string $filename {
        get => $this->filename ??= sprintf("%s_%s.sql", $this->database, new Date()->format("Y-m-d_H-i-s"));
    }
    public URL $fileURL {
        get => $this->fileURL ??= $this->destinationDirectory->appendingPathComponent($this->filename);
    }
    private string $command {
        get => $this->command ??= sprintf("mariadb-dump --user=%s --password=%s --host=%s %s > %s", escapeshellarg($this->user), escapeshellarg($this->password), escapeshellarg($this->host), escapeshellarg($this->database), escapeshellarg($this->fileURL->path));
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
        exec($this->command, $output, $result);
        $result === 0 ?: fatal_error("Database export failed: " . implode("\n", $output));
    }
}
