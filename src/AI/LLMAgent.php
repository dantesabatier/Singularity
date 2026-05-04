<?php

declare(strict_types=1);

namespace App\AI;

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
     * @return ArrayClass<LLMMessage>
     */
    public function run(ArrayClass $messages): ArrayClass
    {
        $history = clone $messages;
        /** @var ArrayClass<LLMMessage> $newMessages */
        $newMessages = new ArrayClass();
        while (true) {
            $turn = $this->client->complete($history, $this->toolRegistry->list);
            $assistantMsg = new LLMMessage("assistant", $turn->text, $turn->toolCalls);
            $history->append($assistantMsg);
            $newMessages->append($assistantMsg);
            if ($turn->isDone) {
                break;
            }
            foreach ($turn->toolCalls as $call) {
                $result = $this->toolRegistry->call($call->name, $call->arguments);
                $text = $result->map(fn(ContentItem $item): string => $item->text)->join("\n");
                $toolMsg = new LLMMessage("tool", $text, toolCallId: $call->id);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
            }
        }
        return $newMessages;
    }
}
