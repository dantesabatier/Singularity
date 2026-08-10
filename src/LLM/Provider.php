<?php

declare(strict_types=1);

namespace App\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\OllamaClient;
use Sabatier\Service\LLM\StandardLLMClient;
use const App\LLMProvidersPreferencesKey;

/**
 * Configuration for an LLM service provider.
 *
 * Holds the endpoint URL, API key, and the list of models the provider exposes.
 * Acts as a factory for the appropriate `LLMClient` implementation via `client()`.
 * Providers are persisted in `UserDefaults` under `LLMProvidersPreferencesKey`; `all()` reads
 * that list and falls back to the built-in Anthropic default when nothing is stored.
 */
final class Provider
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary([
            "name" => $this->name,
            "identifier" => $this->identifier,
            "url" => $this->url,
            "apiKey" => $this->apiKey,
            "models" => $this->models->map(fn(Model $model): Dictionary => $model->dictionaryRepresentation),
            "options" => $this->options,
        ]);
    }

    /**
     * @param ArrayClass<Model> $models
     * @param Dictionary<mixed> $options Campos extra de generación fusionados en el cuerpo de cada petición (p. ej. temperature, o los num_ctx/num_predict de Ollama).
     */
    public function __construct(public readonly string $name, public readonly string $identifier, public readonly URL $url, public readonly string $apiKey, public readonly ArrayClass $models, public readonly Dictionary $options = new Dictionary())
    {
    }

    public function client(?string $model = null): LLMClient
    {
        $client = match ($this->identifier) {
            "anthropic" => new AnthropicClient($model, $this->url, $this->apiKey),
            "ollama" => new OllamaClient($model, $this->url, $this->apiKey),
            default => new StandardLLMClient($model, $this->url, $this->apiKey),
        };
        $client->extraBody = $this->options;
        return $client;
    }

    /**
     * @return ArrayClass<Provider>
     */
    public static function all(): ArrayClass
    {
        if ($stored = UserDefaults::standard()->array(LLMProvidersPreferencesKey)) {
            $providers = $stored->map(fn(Dictionary $dictionary): Provider => ProviderBuilder::build($dictionary));
            if (!$providers->isEmpty) {
                return $providers;
            }
        }
        return self::defaults();
    }

    public static function find(string $identifier): ?self
    {
        return self::all()->first(fn(Provider $provider) => $provider->identifier === $identifier);
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
            url: new URL("https://api.anthropic.com/v1/messages"),
            apiKey: "",
            models: new ArrayClass([new Model("Opus 4.8", "claude-opus-4-8", "opus"), new Model("Sonnet 4.6", "claude-sonnet-4-6", "sonnet"), new Model("Haiku 4.5", "claude-haiku-4-5-20251001", "haiku")])
        );
    }
}
