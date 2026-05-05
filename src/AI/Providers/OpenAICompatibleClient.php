<?php

declare(strict_types=1);

namespace App\AI\Providers;

use App\AI\LLMClient;
use App\AI\LLMMessage;
use App\AI\LLMToolCall;
use App\AI\LLMTurn;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;
use function Sabatier\Foundation\fatal_error;

final class OpenAICompatibleClient extends LLMClient
{
    public string $version = "2022-11-28";
    public int $maxTokens = 8192;

    public function __construct(private readonly ?string $model = null, private readonly ?URL $endpoint = null, private readonly ?string $key = null)
    {
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools): URLRequest
    {
        $request = new URLRequest($this->endpoint ?? fatal_error("Endpoint URL must be provided for OpenAICompatibleClient"));
        $request->httpMethod = HTTPRequestMethod::post;
        $request->allHTTPHeaderFields = new Dictionary([
            "Authorization" => "Bearer $this->key",
            "X-GitHub-Api-Version" => $this->version,
            "Content-Type" => "application/json",
        ]);
        $body = [
            "model" => $this->model,
            "max_tokens" => $this->maxTokens,
            "messages" => $this->formatMessages($messages),
        ];
        if (!$tools->isEmpty) {
            $body["tools"] = $this->formatTools($tools);
        }
        $request->httpBody = (string)json_encode($body);
        return $request;
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @return list<array<string, mixed>>
     */
    private function formatMessages(ArrayClass $messages): array
    {
        $result = [];
        foreach ($messages as $message) {
            if ($message->role === "tool") {
                $result[] = [
                    "role" => "tool",
                    "tool_call_id" => $message->toolCallId ?? "",
                    "content" => $message->content ?? "",
                ];
                continue;
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === "assistant") {
                $calls = [];
                foreach ($toolCalls as $call) {
                    $calls[] = [
                        "id" => $call->id,
                        "type" => "function",
                        "function" => [
                            "name" => $call->name,
                            "arguments" => (string)json_encode($call->arguments->array),
                        ],
                    ];
                }
                $result[] = [
                    "role" => "assistant",
                    "content" => $message->content,
                    "tool_calls" => $calls,
                ];
            } else {
                $result[] = [
                    "role" => $message->role,
                    "content" => $message->content ?? "",
                ];
            }
        }
        return $result;
    }

    /**
     * @param ArrayClass<ToolDescriptor> $tools
     * @return list<array<string, mixed>>
     */
    private function formatTools(ArrayClass $tools): array
    {
        $result = [];
        foreach ($tools as $tool) {
            $result[] = [
                "type" => "function",
                "function" => [
                    "name" => $tool->name,
                    "description" => $tool->description,
                    "parameters" => $this->normalizeSchema($tool->inputSchema),
                ],
            ];
        }
        return $result;
    }

    /**
     * OpenAI requires array-typed properties to have an `items` field.
     * Recursively adds `items: {}` where missing.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        if (($schema["type"] ?? null) === "array" && !array_key_exists("items", $schema)) {
            $schema["items"] = new stdClass();
        }
        if (isset($schema["properties"]) && is_array($schema["properties"])) {
            foreach ($schema["properties"] as $key => $prop) {
                if (is_array($prop)) {
                    $schema["properties"][$key] = $this->normalizeSchema($prop);
                }
            }
        }
        return $schema;
    }

    #[Override]
    protected function parseResponse(Dictionary $body): LLMTurn
    {
        $error = $body["error"];
        !$error ?: fatal_error($error["message"] ?? "Unknown API error");
        $text = null;
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        foreach ($body["choices"] ?? [] as $choice) {
            $message = $choice["message"] ?? [];
            $text = $message["content"] ?? null;
            foreach ($message["tool_calls"] ?? [] as $tc) {
                $fn = $tc["function"] ?? [];
                $args = json_decode($fn["arguments"] ?? "{}", true);
                $toolCalls->append(new LLMToolCall(
                    $tc["id"] ?? "",
                    $fn["name"] ?? "",
                    new Dictionary(is_array($args) ? $args : []),
                ));
            }
            break;
        }
        return new LLMTurn($text, $toolCalls);
    }
}
