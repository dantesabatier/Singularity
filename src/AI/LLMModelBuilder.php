<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\Dictionary;

final class LLMModelBuilder
{
    public static function build(Dictionary $dict): ?LLMModel
    {
        $name = $dict["name"];
        $identifier = $dict["identifier"];
        $tier = $dict["tier"];
        if (!is_string($name) || !is_string($identifier) || !is_string($tier)) {
            return null;
        }
        return new LLMModel($name, $identifier, $tier);
    }
}
