<?php

namespace App\Model;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use const App\AutomaticallyDeleteProjectFoldersPreferencesKey;
use function Sabatier\Foundation\random_color;

/**
 * @property string $name
 * @property string|null $color
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property URL|null $url
 * @property int<0, max> $position
 * @property Model|null $model
 * @property Set<Conversation> $conversations
 * @method void addConversationsObject(Conversation $object)
 * @method void removeConversationsObject(Conversation $object)
 * @method void addConversations(Set<Conversation> $objects)
 * @method void removeConversations(Set<Conversation> $objects)
 * @method Set<Conversation> intersectConversations(Set<Conversation> $objects)
 * @method void setConversations(Set<Conversation> $objects)
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
