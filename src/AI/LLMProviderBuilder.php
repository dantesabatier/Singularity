<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

final class LLMProviderBuilder
{
    public static function build(Dictionary $dict): ?LLMProvider
    {
        $name = $dict["name"];
        $identifier = $dict["identifier"];
        $modelsData = $dict["models"];
        if (!is_string($name) || !is_string($identifier) || !($modelsData instanceof ArrayClass)) {
            return null;
        }
        $models = $modelsData->compactMap(fn(mixed $item): ?LLMModel => $item instanceof Dictionary ? LLMModelBuilder::build($item) : null);
        if ($models->count === 0) {
            return null;
        }
        return new LLMProvider($name, $identifier, $models);
    }
}
