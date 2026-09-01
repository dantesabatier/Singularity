<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\LLM\Provider;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\OllamaClient;
use Sabatier\Service\LLM\StandardLLMClient;

final class ProviderClientTest extends TestCase
{
    #[Test]
    public function browserRepresentationOmitsTheAPIKey(): void
    {
        $provider = new Provider("Test", "anthropic", new URL("https://provider.invalid/messages"), "secret-key", new ArrayClass());
        $engine = new Engine();
        $engine->setLoader(new StringLoader());
        $engine->addFilter("json", json_encode(...));
        $html = $engine->renderToString('<div data-provider="{$provider->redactedDictionaryRepresentation|json}"></div>', ["provider" => $provider]);

        $this->assertSame("secret-key", $provider->dictionaryRepresentation["apiKey"]);
        $this->assertFalse($provider->redactedDictionaryRepresentation->offsetExists("apiKey"));
        $this->assertStringNotContainsString("secret-key", $html);
        $this->assertStringNotContainsString("apiKey", $html);
    }

    /**
     * @param string $identifier The provider identifier selecting the client.
     * @param class-string<LLMClient> $expectedClass The expected implementation.
     */
    #[Test]
    #[DataProvider("providers")]
    public function factoryPreservesConfigurationAndOptsOnlyAnthropicIntoCaching(string $identifier, string $expectedClass): void
    {
        $endpoint = new URL("https://provider.invalid/messages");
        $options = new Dictionary(["temperature" => 0.25, "system" => "Custom instructions"]);
        $provider = new Provider("Test", $identifier, $endpoint, "test-key", new ArrayClass(), $options);
        $client = $provider->client("selected-model");

        $this->assertInstanceOf($expectedClass, $client);
        $this->assertSame("selected-model", $client->model);
        $this->assertSame($endpoint, $client->endpoint);
        $this->assertSame("test-key", $client->key);
        $this->assertSame($options, $client->extraBody);
        if ($client instanceof AnthropicClient) {
            $this->assertTrue($client->cachePromptPrefix);
        } else {
            $this->assertFalse(property_exists($client, "cachePromptPrefix"));
        }
        $this->assertSame("Custom instructions", $client->extraBody["system"]);
    }

    /** @return array<string, array{string, class-string<LLMClient>}> */
    public static function providers(): array
    {
        return [
            "Anthropic" => ["anthropic", AnthropicClient::class],
            "Ollama" => ["ollama", OllamaClient::class],
            "Compatible" => ["compatible", StandardLLMClient::class],
        ];
    }
}
