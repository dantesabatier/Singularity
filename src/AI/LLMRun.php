<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\ArrayClass;

final readonly class LLMRun
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     */
    public function __construct(public ArrayClass $messages, public int $inputTokens = 0, public int $outputTokens = 0)
    {
    }
}
