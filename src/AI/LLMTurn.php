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
     */
    public function __construct(public ?string $text, public ArrayClass $toolCalls)
    {
        $this->isDone = $this->toolCalls->isEmpty;
    }
}
