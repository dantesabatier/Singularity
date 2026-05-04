<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Providers\AnthropicClient;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\UserDefaults;
use const App\EditorAIProvidersPreferencesKey;

final class LLMProvider
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary([
            "name" => $this->name,
            "identifier" => $this->identifier,
            "models" => $this->models->map(fn(LLMModel $model): Dictionary => $model->dictionaryRepresentation),
        ]);
    }

    /**
     * @param string $name
     * @param string $identifier
     * @param ArrayClass<LLMModel> $models
     */
    public function __construct(public readonly string $name, public readonly string $identifier, public readonly ArrayClass $models)
    {
    }

    public function client(?string $model = null): LLMClient
    {
        return match ($this->identifier) {
            "anthropic" => new AnthropicClient($model),
            default => throw new InternalInconsistencyException("Unknown LLM provider: $this->identifier"),
        };
    }

    /**
     * @return ArrayClass<LLMProvider>
     */
    public static function all(): ArrayClass
    {
        if ($stored = UserDefaults::standard()->array(EditorAIProvidersPreferencesKey)) {
            $providers = $stored->compactMap(fn(Dictionary $item): ?LLMProvider => LLMProviderBuilder::build($item));
            if (!$providers->isEmpty) {
                return $providers;
            }
        }
        return self::defaults();
    }

    public static function find(string $identifier): ?self
    {
        return self::all()->first(fn(LLMProvider $provider) => $provider->identifier === $identifier);
    }

    /** @return ArrayClass<self> */
    private static function defaults(): ArrayClass
    {
        return new ArrayClass([self::anthropic()]);
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
