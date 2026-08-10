<?php

declare(strict_types=1);

namespace App\LLM;

use Sabatier\Foundation\Dictionary;

/** Describes an available model within a provider, with a human-readable name and the API identifier sent in requests. */
final class Model
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary(["name" => $this->name, "identifier" => $this->identifier]);
    }

    /**
     * @param string $name The human-readable model name.
     * @param string $identifier The API identifier sent in requests.
     */
    public function __construct(public readonly string $name, public readonly string $identifier)
    {
    }
}
