<?php

declare(strict_types=1);

namespace App\Model;

use Sabatier\Foundation\ArrayClass;

/** Pairs the message that carries a round's text with every tool call folded into that round. */
final class MessageRound
{
    public int $pendingToolCallCount {
        get => $this->toolCalls->filter(fn(ToolCall $toolCall): bool => $toolCall->status === ToolCallStatus::pending)->count;
    }

    public int $failedToolCallCount {
        get => $this->toolCalls->filter(fn(ToolCall $toolCall): bool => $toolCall->status === ToolCallStatus::error)->count;
    }

    /**
     * @param Message $message The message that carries the round's text and identity.
     * @param ArrayClass<ToolCall> $toolCalls Every tool call of the round, including those folded in from preceding text-less messages.
     */
    public function __construct(public readonly Message $message, public readonly ArrayClass $toolCalls)
    {
    }
}
