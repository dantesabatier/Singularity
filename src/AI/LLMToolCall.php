<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\Dictionary;

final readonly class LLMToolCall
{
    /**
     * @param string $id
     * @param string $name
     * @param Dictionary<mixed> $arguments
     */
    public function __construct(public string $id, public string $name, public Dictionary $arguments)
    {
    }
}
