<?php

declare(strict_types=1);

namespace App\FileWriters;

use App\Model\ModelMap;
use Override;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\URL;

/**
 * Emits the archived mapping model a migration reads.
 *
 * The file is located by the entity version hashes its mappings carry, never by name, so where it
 * sits only matters in that the bundle must be able to enumerate it: beside the model, in the
 * project's resources.
 */
final class ModelMapFileWriter extends FileWriter
{
    #[Override]
    public string $contents {
        get => KeyedArchiver::archivedData($this->modelMap->mappingModel);
    }
    private readonly ModelMap $modelMap;

    /**
     * @param URL $url The destination file this writer generates.
     * @param ModelMap $modelMap The map whose mapping model is archived to the file.
     */
    public function __construct(URL $url, ModelMap $modelMap)
    {
        parent::__construct($url);
        $this->modelMap = $modelMap;
    }
}
