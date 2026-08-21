<?php

declare(strict_types=1);

namespace App\Bundles;

use App\Model\Project;
use Exception;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\CoreData\SQLCore;
use Sabatier\CoreData\SQLModel;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\parse_env_file;
use const Sabatier\CoreData\SQLSchemaName;

/**
 * Writes the model a project's store holds as its cached one.
 *
 * The store decides whether to migrate by comparing that model against the one on disk, so putting
 * a model here is what says "this store is already at this version" — or, with the previous
 * version, what asks for the migration to run.
 *
 * The connection host and credentials come from the environment Singularity itself runs in, since
 * that is what the SQL layer reads; only the schema name is taken from the project. A project on a
 * different server would be written to Singularity's own.
 */
final class CachedModelWriter
{
    private string $schemaName {
        get {
            $path = $this->project->url?->appendingPathComponent(".env")?->path;
            if (!$path || !FileManager::default()->fileExists($path)) {
                return $this->project->name;
            }
            /** @var string $name */
            $name = Dictionary::dictionaryWithArray(parse_env_file($path))[SQLSchemaName] ?? $this->project->name;
            return $name;
        }
    }

    /**
     * @param Project $project The project whose store is written to.
     */
    public function __construct(private readonly Project $project)
    {
    }

    /**
     * @throws Exception
     */
    public function write(ManagedObjectModel $managedObjectModel): void
    {
        $schemaName = $this->schemaName;
        $coordinator = new PersistentStoreCoordinator($managedObjectModel);
        $core = new SQLCore($coordinator, $schemaName, new URL("sql://$schemaName"));
        $connection = $core->schemaValidationConnection;
        $connection->connect();
        $connection->saveCachedModel(new SQLModel($managedObjectModel, $schemaName));
    }
}
