<?php

declare(strict_types=1);

namespace App\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/** Reconstructs a Provider from its dictionary representation (as produced by `Provider::$dictionaryRepresentation`). */
final class ProviderBuilder
{
    public static function build(Dictionary $dictionary): Provider
    {
        /** @var string $name */
        $name = $dictionary["name"] ?? "";
        /** @var string $identifier */
        $identifier = $dictionary["identifier"] ?? "";
        $url = new URL($dictionary["url"] ?? "https://api.provider.com/v1");
        /** @var string $apiKey */
        $apiKey = $dictionary["apiKey"] ?? "";
        /** @var ArrayClass<Dictionary<mixed>> $models */
        $models = $dictionary["models"] ?? new ArrayClass();
        // A provider with no options is stored as an empty dictionary, but the format doesn't distinguish "{}" from "[]": empty materializes as a sequential ArrayClass. We normalize it to Dictionary for the constructor.
        $storedOptions = $dictionary["options"];
        $options = $storedOptions instanceof Dictionary ? $storedOptions : new Dictionary($storedOptions ?? []);
        return new Provider($name, $identifier, $url, $apiKey, $models->map(fn(Dictionary $model): Model => ModelBuilder::build($model)), $options);
    }
}
