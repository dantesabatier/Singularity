<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Providers\AnthropicClient;

enum LLMProvider: string
{
    case anthropic = "anthropic";

    public function client(?string $model = null): LLMClient
    {
        return match ($this) {
            self::anthropic => new AnthropicClient($model),
        };
    }
}
