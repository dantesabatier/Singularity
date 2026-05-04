<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Providers\AnthropicClient;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\InternalInconsistencyException;

final readonly class LLMProvider
{
    /** @param ArrayClass<LLMModel> $models */
    public function __construct(public string $name, public string $identifier, public ArrayClass $models)
    {
    }

    public function client(?string $model = null): LLMClient
    {
        return match ($this->identifier) {
            "anthropic" => new AnthropicClient($model),
            default => throw new InternalInconsistencyException("Unknown LLM provider: $this->identifier"),
        };
    }

    /** @return ArrayClass<self> */
    public static function all(): ArrayClass
    {
        return new ArrayClass([self::anthropic()]);
    }

    public static function find(string $identifier): ?self
    {
        foreach (self::all() as $provider) {
            if ($provider->identifier === $identifier) {
                return $provider;
            }
        }
        return null;
    }

    public static function anthropic(): self
    {
        return new self(
            name: "Anthropic",
            identifier: "anthropic",
            models: new ArrayClass([new LLMModel("Opus 4.7", "claude-opus-4-7", "opus"), new LLMModel("Sonnet 4.6", "claude-sonnet-4-6", "sonnet"), new LLMModel("Haiku 4.5", "claude-haiku-4-5-20251001", "haiku")])
        );
    }
}
