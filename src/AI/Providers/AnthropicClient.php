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
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\URL;
use Sabatier\Service\InternalServerErrorException;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use function Sabatier\Foundation\fatal_error;

final class AnthropicClient extends LLMClient
{
    #[Override]
    public string $version = "2023-06-01";
    #[Override]
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
        $request = new URLRequest($this->endpoint ?? fatal_error("Endpoint URL must be provided for AnthropicClient"));
        $request->httpMethod = HTTPRequestMethod::post;
        $request->allHTTPHeaderFields = new Dictionary([
            "x-api-key" => $this->key,
            "anthropic-version" => $this->version,
            "Content-Type" => "application/json",
        ]);
        $request->httpBody = (string)json_encode([
            "model" => $this->model,
            "max_tokens" => $this->maxTokens,
            "messages" => $this->formatMessages($messages),
            "tools" => $this->formatTools($tools),
        ]);
        return $request;
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @return list<array<string, mixed>>
     */
    private function formatMessages(ArrayClass $messages): array
    {
        $result = [];
        $pendingToolResults = [];
        foreach ($messages as $message) {
            if ($message->role === "tool") {
                $pendingToolResults[] = [
                    "type" => "tool_result",
                    "tool_use_id" => $message->toolCallId ?? "",
                    "content" => $message->content ?? "",
                ];
                continue;
            }
            if ($pendingToolResults !== []) {
                $result[] = ["role" => "user", "content" => $pendingToolResults];
                $pendingToolResults = [];
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === "assistant") {
                $content = [];
                if ($message->content !== null && $message->content !== "") {
                    $content[] = ["type" => "text", "text" => $message->content];
                }
                foreach ($toolCalls as $call) {
                    $content[] = [
                        "type" => "tool_use",
                        "id" => $call->id,
                        "name" => $call->name,
                        "input" => $call->arguments->array,
                    ];
                }
                $result[] = ["role" => "assistant", "content" => $content];
            } else {
                $result[] = ["role" => $message->role, "content" => $message->content ?? ""];
            }
        }
        if ($pendingToolResults !== []) {
            $result[] = ["role" => "user", "content" => $pendingToolResults];
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
                "name" => $tool->name,
                "description" => $tool->description,
                "input_schema" => $tool->inputSchema,
            ];
        }
        return $result;
    }

    #[Override]
    protected function parseResponse(Dictionary $body): LLMTurn
    {
        if ($body["type"] === "error") {
            $error = $body["error"];
            $message = is_array($error) ? ($error["message"] ?? "Unknown API error") : "Unknown API error";
            throw new InternalServerErrorException($message);
        }
        $text = null;
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        /** @var list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}> $content */
        $content = $body["content"] ?? [];
        foreach ($content as $block) {
            match ($block["type"] ?? "") {
                "text" => $text = $block["text"] ?? null,
                "tool_use" => $toolCalls->append(new LLMToolCall(
                    $block["id"] ?? "",
                    $block["name"] ?? "",
                    new Dictionary($block["input"] ?? []),
                )),
                default => null,
            };
        }
        if ($text instanceof Nil) {
            $text = null;
        }
        return new LLMTurn($text, $toolCalls);
    }
}
