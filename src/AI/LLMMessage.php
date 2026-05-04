<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\ArrayClass;

final readonly class LLMMessage
{
    /**
     * @param string $role
     * @param string|null $content
     * @param ArrayClass<LLMToolCall>|null $toolCalls
     * @param string|null $toolCallId
     */
    public function __construct(public string $role, public ?string $content, public ?ArrayClass $toolCalls = null, public ?string $toolCallId = null)
    {
    }
}
