<?php

namespace App\Model;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\AutomaticallyDeleteProjectFolders;

/**
 * @property string $name
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property URL|null $url
 * @property Model|null $model
 * @property string|null $color
 */
class Project extends ManagedObject
{
    #[Override]
    public function awakeFromInsert(): void
    {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function prepareForDeletion(): void
    {
        if (UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders) && ($url = $this->url) && FileManager::default()->fileExists($url->path, $isDirectory) && $isDirectory) {
            FileManager::default()->removeItem($url);
        }
    }
}
