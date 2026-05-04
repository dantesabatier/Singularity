<?php

declare(strict_types=1);

namespace App\Model;

use App\AI\LLMMessage;
use App\AI\LLMToolCall;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;

/**
 * @property Date $creationDate
 * @property string $role
 * @property string|null $content
 * @property string|null $toolCalls
 * @property string|null $toolCallId
 * @property int<0, max> $position
 * @property Conversation|null $conversation
 */
final class Message extends ManagedObject
{
    public LLMMessage $LLMMessage {
        get {
            $toolCalls = null;
            if ($calls = $this->toolCalls) {
                $toolCalls = new ArrayClass(json_decode($calls, true) ?? [])->map(fn(array $tc): LLMToolCall => new LLMToolCall($tc["id"] ?? "", $tc["name"] ?? "", new Dictionary($tc["arguments"] ?? [])));
            }
            return new LLMMessage($this->role, $this->content, $toolCalls, $this->toolCallId);
        }
        set {
            $this->role = $value->role;
            $this->content = $value->content;
            if ($toolCalls = $value->toolCalls) {
                $this->toolCalls = json_encode($toolCalls->array);
            }
            $this->toolCallId = $value->toolCallId;
        }
    }
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["role" => $this->role, "content" => $this->content, "toolCalls" => $this->toolCalls ? json_decode($this->toolCalls, true) : null, "toolCallId" => $this->toolCallId]);
    }

    #[Override]
    public function willSave(): void
    {
        $this->role = $this->role |> trim(...);
        if ($this->content) {
            $this->content = $this->content |> trim(...);
        }
        if ($this->toolCallId) {
            $this->toolCallId = $this->toolCallId |> trim(...);
        }
    }
}
