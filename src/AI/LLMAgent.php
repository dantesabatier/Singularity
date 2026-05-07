<?php

declare(strict_types=1);

namespace App\AI;

use App\Model\MessageRole;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final readonly class LLMAgent
{
    public function __construct(private LLMClient $client, private ToolRegistry $toolRegistry)
    {
    }

    /**
     * Runs the agentic loop and returns only the new messages generated (not the input).
     *
     * @param ArrayClass<LLMMessage> $messages
     */
    public function run(ArrayClass $messages, ?string $systemPrompt = null): LLMRun
    {
        $history = clone $messages;
        /** @var ArrayClass<LLMMessage> $newMessages */
        $newMessages = new ArrayClass();
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        while (true) {
            $turn = $this->client->complete($history, $this->toolRegistry->list, $systemPrompt);
            $totalInputTokens += $turn->inputTokens;
            $totalOutputTokens += $turn->outputTokens;
            $assistantMsg = new LLMMessage(MessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens);
            $history->append($assistantMsg);
            $newMessages->append($assistantMsg);
            if ($turn->isDone) {
                break;
            }
            foreach ($turn->toolCalls as $call) {
                $result = $this->toolRegistry->call($call->name, $call->arguments);
                $text = $result->map(fn(ContentItem $item): string => $item->text)->join("\n");
                $toolMsg = new LLMMessage(MessageRole::tool, $text, toolCallId: $call->id);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
            }
        }
        return new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens);
    }
}
