<?php

declare(strict_types=1);

namespace App\AI;

use Sabatier\Foundation\Dictionary;

final class LLMModelBuilder
{
    public static function build(Dictionary $dictionary): LLMModel
    {
        return new LLMModel($dictionary["name"] ?? "",  $dictionary["identifier"] ?? "", $dictionary["tier"] ?? "");
    }
}
