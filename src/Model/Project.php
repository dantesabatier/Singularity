<?php

namespace App\Model;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\random_color;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;

/**
 * @property string $name
 * @property string|null $color
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property URL|null $url
 * @property int<0, max> $position
 * @property Model|null $model
 */
final class Project extends ManagedObject
{
    #[Override]
    public function willSave(): void
    {
        $this->name = $this->name |> trim(...);
        $this->color ??= random_color($this->name);
        $this->lastModifiedDate = new Date();
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function prepareForDeletion(): void
    {
        if (UserDefaults::standard()->bool(AutomaticallyDeleteProjectFoldersPreferencesKey) && ($url = $this->url) && FileManager::default()->fileExists($url->path, $isDirectory) && $isDirectory) {
            FileManager::default()->removeItem($url);
        }
    }
}
