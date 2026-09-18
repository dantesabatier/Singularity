<?php

declare(strict_types=1);

namespace App\Tests\Unit\LLM;

use App\LLM\Model;
use App\LLM\Provider;
use App\LLM\ProviderBuilder;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\StandardLLMClient;

final class ProviderTest extends TestCase
{
    private function sampleProvider(string $identifier = "acme"): Provider
    {
        return new Provider(
            name: "Acme",
            identifier: $identifier,
            url: new URL("https://api.acme.test/v1/messages"),
            apiKey: "secret-key",
            models: new ArrayClass([new Model("Big", "acme-big", "opus"), new Model("Small", "acme-small", "haiku")])
        );
    }

    /** The wire form persisted by PreferencesController: url is a string, models are dictionaries. */
    private function wireDictionary(): Dictionary
    {
        return new Dictionary([
            "name" => "Acme",
            "identifier" => "acme",
            "url" => "https://api.acme.test/v1/messages",
            "apiKey" => "secret-key",
            "models" => new ArrayClass([
                new Dictionary(["name" => "Big", "identifier" => "acme-big"]),
                new Dictionary(["name" => "Small", "identifier" => "acme-small"]),
            ]),
        ]);
    }

    public function testBuildReconstructsAProviderFromItsWireDictionary(): void
    {
        $rebuilt = ProviderBuilder::build($this->wireDictionary());

        self::assertSame("Acme", $rebuilt->name);
        self::assertSame("acme", $rebuilt->identifier);
        self::assertSame("https://api.acme.test/v1/messages", $rebuilt->url->absoluteString);
        self::assertSame("secret-key", $rebuilt->apiKey);
        self::assertSame(2, $rebuilt->models->count);
        self::assertSame("acme-big", $rebuilt->models->first?->identifier);
        self::assertSame("acme-small", $rebuilt->models->last?->identifier);
    }

    public function testDictionaryRepresentationExposesEveryFieldToTheFrontend(): void
    {
        $dictionary = $this->sampleProvider()->dictionaryRepresentation;

        self::assertSame("Acme", $dictionary["name"]);
        self::assertSame("acme", $dictionary["identifier"]);
        self::assertSame("secret-key", $dictionary["apiKey"]);
        self::assertInstanceOf(URL::class, $dictionary["url"]);
        self::assertSame(2, $dictionary["models"]->count);
        self::assertSame("Big", $dictionary["models"]->first?->offsetGet("name"));
    }

    public function testBuildDefaultsTheUrlWhenAbsent(): void
    {
        $provider = ProviderBuilder::build(new Dictionary(["name" => "Bare", "identifier" => "bare"]));
        self::assertSame("https://api.provider.com/v1", $provider->url->absoluteString);
        self::assertSame("", $provider->apiKey);
        self::assertTrue($provider->models->isEmpty);
    }

    public function testAnthropicIdentifierYieldsAnAnthropicClient(): void
    {
        $provider = $this->sampleProvider("anthropic");
        self::assertInstanceOf(AnthropicClient::class, $provider->client());
    }

    public function testAnUnknownIdentifierYieldsTheStandardClient(): void
    {
        $provider = $this->sampleProvider("openai");
        self::assertInstanceOf(StandardLLMClient::class, $provider->client("some-model"));
    }

    public function testTheBuiltInAnthropicDefaultIsWellFormed(): void
    {
        $anthropic = Provider::anthropic();
        self::assertSame("anthropic", $anthropic->identifier);
        self::assertSame("https://api.anthropic.com/v1/messages", $anthropic->url->absoluteString);
        self::assertFalse($anthropic->models->isEmpty);
        self::assertInstanceOf(AnthropicClient::class, $anthropic->client());
    }
}
