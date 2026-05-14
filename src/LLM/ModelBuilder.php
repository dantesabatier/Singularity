<?php

declare(strict_types=1);

namespace App\LLM;

use Sabatier\Foundation\Dictionary;

/** Reconstructs a Model from its dictionary representation (as produced by `Model::$dictionaryRepresentation`). */
final class ModelBuilder
{
    public static function build(Dictionary $dictionary): Model
    {
        return new Model($dictionary["name"] ?? "", $dictionary["identifier"] ?? "", $dictionary["tier"] ?? "");
    }
}
