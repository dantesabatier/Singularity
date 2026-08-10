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
        $name = $dictionary["name"] ?? "";
        $identifier = $dictionary["identifier"] ?? "";
        $url = new URL($dictionary["url"] ?? "https://api.provider.com/v1");
        $apiKey = $dictionary["apiKey"] ?? "";
        /** @var ArrayClass<Dictionary<mixed>> $models */
        $models = $dictionary["models"] ?? new ArrayClass();
        // Un provider sin opciones se almacena como diccionario vacío, pero el
        // formato no distingue "{}" de "[]": el vacío se materializa como
        // ArrayClass secuencial. Lo normalizamos a Dictionary para el constructor.
        $storedOptions = $dictionary["options"] ?? null;
        $options = $storedOptions instanceof Dictionary ? $storedOptions : new Dictionary($storedOptions ?? new ArrayClass());
        return new Provider($name, $identifier, $url, $apiKey, $models->map(fn(Dictionary $model): Model => ModelBuilder::build($model)), $options);
    }
}
