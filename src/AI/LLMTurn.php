<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\ArrayClass;

final readonly class LLMTurn
{
    public bool $isDone;

    /**
     * @param string|null $text
     * @param ArrayClass<LLMToolCall> $toolCalls
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     */
    public function __construct(public ?string $text, public ArrayClass $toolCalls, public int $inputTokens = 0, public int $outputTokens = 0)
    {
        $this->isDone = $this->toolCalls->isEmpty;
    }
}
