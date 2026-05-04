<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\Dictionary;

final class LLMModel
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["name" => $this->name, "identifier" => $this->identifier, "tier" => $this->tier]);
    }

    public function __construct(public readonly string $name, public readonly string $identifier, public readonly string $tier)
    {
    }
}
