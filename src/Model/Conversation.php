<?php

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @property Date $creationDate
 * @property Date $lastModifiedDate
 * @property string $title
 * @property string $provider
 * @property string $model
 * @property int<0, max> $totalTokens
 * @property int<0, max> $inputTokens
 * @property int<0, max> $outputTokens
 * @property float $cost
 * @property Project|null $project
 * @property Set<Message> $messages
 * @method void addMessagesObject(Message $object)
 * @method void removeMessagesObject(Message $object)
 * @method void addMessages(Set<Message> $objects)
 * @method void removeMessages(Set<Message> $objects)
 * @method Set<Message> intersectMessages(Set<Message> $objects)
 * @method void setMessages(Set<Message> $objects)
 */
final class Conversation extends ManagedObject
{
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["objectID" => $this->objectID, "title" => $this->title, "provider" => $this->provider, "model" => $this->model, "messages" => $this->messages->map(fn(Message $message): Dictionary => $message->dictionaryRepresentation)]);
    }

    #[Override]
    public function willSave(): void
    {
        $this->title = $this->title |> trim(...);
        $this->lastModifiedDate = new Date();
    }
}
