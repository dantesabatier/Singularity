<?php

declare(strict_types=1);

namespace App\AI;

use App\Model\MessageRole;
use Sabatier\Foundation\ArrayClass;

final readonly class LLMMessage
{
    /**
     * @param MessageRole $role
     * @param string|null $content
     * @param ArrayClass<LLMToolCall>|null $toolCalls
     * @param string|null $toolCallId
     */
    /**
     * @param ArrayClass<array{name: string, mimeType: string, data: string}>|null $images
     */
    public function __construct(public MessageRole $role, public ?string $content, public ?ArrayClass $toolCalls = null, public ?string $toolCallId = null, public ?ArrayClass $images = null, public int $outputTokens = 0)
    {
    }
}
