<?php

declare(strict_types=1);

namespace App\Bundles;

use App\FileWriters\ModelMapFileWriter;
use App\Model\ModelMap;
use Exception;
use Override;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;

/**
 * Hands a project's store over to the version its map arrives at.
 *
 * While the model is being edited the store is kept at the version it already holds, so nothing
 * migrates under the programmer's feet. This is the act that asks for the migration: the mapping
 * model is written where the store will look for it, and the version the map starts from goes back
 * into the store's cached model. The next time the store opens it finds the two versions apart and
 * the map that spans them.
 */
final readonly class UpgradeModelTransaction implements Transaction
{
    /** @var URL The mapping model this upgrade migrates with. */
    public URL $mappingModelURL;

    /**
     * @param ModelMap $modelMap The map the store is upgraded through.
     */
    public function __construct(private ModelMap $modelMap)
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function execute(): void
    {
        $project = $this->modelMap->project ?? fatal_error("Mapping model does not belong to a project");
        $sourceModel = $this->modelMap->sourceModel ?? fatal_error("Mapping model has no source version to migrate from");
        $mappingModelURL = $this->modelMap->mappingModelURL ?? fatal_error("Mapping model URL is required to upgrade a model");
        new ModelMapFileWriter($mappingModelURL, $this->modelMap)->save();
        new CachedModelWriter($project)->write($sourceModel);
        $this->mappingModelURL = $mappingModelURL;
    }
}
