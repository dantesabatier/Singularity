<?php

declare(strict_types=1);

namespace App\Model;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;

/**
 * @property string $name
 * @property string $identifier
 * @property Date $executionDate
 * @property ToolCallStatus $status
 * @property Dictionary<mixed> $arguments
 * @property string|null $result
 * @property Message|null $message
 */
final class ToolCall extends ManagedObject
{
    /** History must close unanswered calls too, without executing them on continuation. */
    public LLMMessage $LLMResult {
        get => new LLMMessage(LLMMessageRole::tool, $this->result ?? "No result was received before the previous run stopped. Do not assume this call succeeded.", toolCallId: $this->identifier, isError: $this->result === null || $this->status === ToolCallStatus::error);
    }

    /** @var Dictionary<mixed> */
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["id" => $this->identifier, "name" => $this->name, "input" => $this->arguments, "result" => $this->result, "status" => $this->status->value, "isError" => $this->status === ToolCallStatus::error]);
    }

    public function validateStatus(ToolCallStatus|int|null &$status): bool
    {
        if (is_int($status)) {
            $status = ToolCallStatus::from($status);
        }
        return true;
    }
}
