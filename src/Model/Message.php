<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace App\Model;

use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URLFileTypeMappings;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;

/**
 * @property Date $creationDate
 * @property LLMMessageRole $role
 * @property string|null $content
 * @property Date|null $lastModifiedDate
 * @property int<0, max> $tokenCount
 * @property Conversation|null $conversation
 * @property Set<ToolCall> $toolCalls
 * @property Set<Attachment> $attachments
 * @method void addAttachmentsObject(Attachment $object)
 * @method void removeAttachmentsObject(Attachment $object)
 * @method void addAttachments(Set<Attachment> $objects)
 * @method void removeAttachments(Set<Attachment> $objects)
 * @method Set<Attachment> intersectAttachments(Set<Attachment> $objects)
 * @method void setAttachments(Set<Attachment> $objects)
 * @method void addToolCallsObject(ToolCall $object)
 * @method void removeToolCallsObject(ToolCall $object)
 * @method void addToolCalls(Set<ToolCall> $objects)
 * @method void removeToolCalls(Set<ToolCall> $objects)
 * @method Set<ToolCall> intersectToolCalls(Set<ToolCall> $objects)
 * @method void setToolCalls(Set<ToolCall> $objects)
 */
final class Message extends ManagedObject
{
    public LLMMessage $LLMMessage {
        get => new LLMMessage($this->role, $this->content, new ArrayClass($this->toolCalls->map(fn(ToolCall $toolCall): LLMToolCall => new LLMToolCall($toolCall->identifier, $toolCall->name, $toolCall->arguments))), null, new ArrayClass($this->attachments->compactMap(function (Attachment $attachment): ?Dictionary {
            $fileManager = FileManager::default();
            if (!($url = $attachment->url)) {
                return null;
            }
            if (!($data = $fileManager->contents($url->path))) {
                return null;
            }
            return new Dictionary(["mimeType" => URLFileTypeMappings::shared()->mimeType($url->pathExtension) ?? "application/octet-stream", "data" => base64_encode($data)]);
        })));
        set {
            $this->role = $value->role;
            $this->content = $value->content;
            $this->tokenCount = $value->outputTokens;
            $this->addToolCalls(new Set($value->toolCalls?->map(function (LLMToolCall $llmToolCall): ToolCall {
                $toolCall = new ToolCall($this->managedObjectContext);
                $toolCall->identifier = $llmToolCall->id;
                $toolCall->name = $llmToolCall->name;
                $toolCall->arguments = $llmToolCall->arguments;
                return $toolCall;
            }) ?? []));
        }
    }
    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["objectID" => $this->objectID, "role" => $this->role, "content" => $this->content, "toolCalls" => $this->toolCalls->map(fn(ToolCall $toolCall): Dictionary => $toolCall->dictionaryRepresentation)]);
    }

    #[Override]
    public function willSave(): void
    {
        if ($this->content !== null) {
            $this->content = $this->content |> trim(...);
        }
        $this->lastModifiedDate = new Date();
    }

    public function validateRole(LLMMessageRole|string|null &$role): bool
    {
        if (is_string($role)) {
            $role = LLMMessageRole::from($role);
        }
        return true;
    }
}
