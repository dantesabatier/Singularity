<?php

namespace App\Model;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\AutomaticallyDeleteProjectFolders;

/**
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property string|null $name
 * @property URL|null $url
 * @property Model|null $model
 */
class Project extends ManagedObject
{
    public function prepareForDeletion(): void
    {
        if (UserDefaults::standard()->bool(AutomaticallyDeleteProjectFolders) && ($url = $this->url) && FileManager::default()->fileExists($url->path, $isDirectory) && $isDirectory) {
            try {
                FileManager::default()->removeItem($url);
            } catch (Exception) {
            }
        }
    }
}
