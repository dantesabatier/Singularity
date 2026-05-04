<?php

declare(strict_types=1);

namespace App\AI;

final readonly class LLMModel
{
    public function __construct(public string $name, public string $identifier, public string $tier)
    {
    }
}
