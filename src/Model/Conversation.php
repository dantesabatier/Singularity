<?php

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\LLM\LLMMessageRole;

/**
 * @property Date $creationDate
 * @property Date|null $lastModifiedDate
 * @property string $title
 * @property string $provider
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
        get => new Dictionary(["objectID" => $this->objectID, "title" => $this->title, "provider" => $this->provider, "messages" => $this->messages->map(fn(Message $message): Dictionary => $message->dictionaryRepresentation)]);
    }
    /**
     * The agent loop persists one message per round, so a reply that queried three times arrives as three separate "Used 1 tool" messages. This folds the consecutive rounds that carry no text onto the next assistant message that does, which reads as a single "Used 3 tools". A message with content closes the group — its text and its calls belong together. When the last round is left without a reply (an error, or still in flight), the message carrying it is kept so the block is not lost.
     *
     * @var ArrayClass<MessageRound>
     */
    public ArrayClass $rounds {
        get {
            if (isset($this->rounds)) {
                return $this->rounds;
            }
            /** @var ArrayClass<MessageRound> $rounds */
            $rounds = new ArrayClass();
            /** @var ArrayClass<ToolCall> $pending */
            $pending = new ArrayClass();
            $carrier = null;
            foreach ($this->messages as $message) {
                if ($message->role === LLMMessageRole::assistant && $message->content === null) {
                    $pending->appendContentsOf($message->toolCalls);
                    $carrier ??= $message;
                    continue;
                }
                if (!$pending->isEmpty && $message->role === LLMMessageRole::assistant) {
                    $rounds->append(new MessageRound($message, $pending->appendingContentsOf($message->toolCalls)));
                } else {
                    if ($carrier !== null) {
                        $rounds->append(new MessageRound($carrier, $pending));
                    }
                    $rounds->append(new MessageRound($message, new ArrayClass($message->toolCalls)));
                }
                $pending = new ArrayClass();
                $carrier = null;
            }
            if ($carrier !== null) {
                $rounds->append(new MessageRound($carrier, $pending));
            }
            return $this->rounds = $rounds;
        }
    }

    #[Override]
    public function willSave(): void
    {
        $this->title = $this->title |> trim(...);
        $this->lastModifiedDate = new Date();
    }
}
