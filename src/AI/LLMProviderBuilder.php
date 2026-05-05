<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

final class LLMProviderBuilder
{
    public static function build(Dictionary $dictionary): LLMProvider
    {
        $name = $dictionary["name"] ?? "";
        $identifier = $dictionary["identifier"] ?? "";
        $url = new URL($dictionary["url"] ?? "https://api.provider.com/v1");
        $apiKey = $dictionary["apiKey"] ?? "";
        /** @var ArrayClass<Dictionary<mixed>> $models */
        $models = $dictionary["models"] ?? new ArrayClass();
        return new LLMProvider($name, $identifier, $url, $apiKey, $models->map(fn(Dictionary $model): LLMModel => LLMModelBuilder::build($model)));
    }
}
