<?php

declare(strict_types=1);

namespace App\LLM;

use Sabatier\Foundation\Dictionary;

/** Describes an available model within a provider, with a human-readable name, the API identifier sent in requests, and a capability tier (e.g. "opus", "sonnet", "haiku"). */
final class Model
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["name" => $this->name, "identifier" => $this->identifier, "tier" => $this->tier]);
    }

    /**
     * @param string $name The human-readable model name.
     * @param string $identifier The API identifier sent in requests.
     * @param string $tier The capability tier (e.g. "opus", "sonnet", "haiku").
     */
    public function __construct(public readonly string $name, public readonly string $identifier, public readonly string $tier)
    {
    }
}
