<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Model\ToolCall;
use App\Model\ToolCallStatus;
use App\Model\Message;
use App\Model\MessageRound;
use App\Tests\Support\CoreDataTestCase;
use Latte\Engine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\LLM\LLMMessageRole;

final class ToolCallResultTest extends CoreDataTestCase
{
    #[Test]
    #[DataProvider("results")]
    public function historyReportsUnansweredCallsWithoutChangingTheirStoredResult(ToolCallStatus $status, ?string $content, bool $isError): void
    {
        $call = new ToolCall($this->context);
        $call->identifier = "call-1";
        $call->name = "fetch";
        $call->arguments = new Dictionary();
        $call->status = $status;
        $call->result = $content;

        $message = $call->LLMResult;
        $this->assertSame(LLMMessageRole::tool, $message->role);
        $this->assertSame("call-1", $message->toolCallId);
        $this->assertSame($isError, $message->isError);
        $this->assertSame($content ?? "No result was received before the previous run stopped. Do not assume this call succeeded.", $message->content);
        $this->assertSame($content, $call->result);
        $this->assertSame($status, $call->status);
        $this->assertSame($status->value, $call->dictionaryRepresentation["status"]);
        $this->assertSame($status === ToolCallStatus::error, $call->dictionaryRepresentation["isError"]);
    }

    /** @return array<string, array{ToolCallStatus, ?string, bool}> */
    public static function results(): array
    {
        return [
            "pending" => [ToolCallStatus::pending, null, true],
            "completed" => [ToolCallStatus::completed, "Rows", false],
            "empty success" => [ToolCallStatus::completed, "", false],
            "failure" => [ToolCallStatus::error, "Invalid input", true],
            "legacy missing result" => [ToolCallStatus::completed, null, true],
        ];
    }

    /**
     * Psalm retains assertion-narrowed hook values across changes to the related tool call.
     * @psalm-suppress DocblockTypeContradiction
     */
    #[Test]
    public function roundCountsReflectPendingAndFailedCallsWithoutStaleCaching(): void
    {
        $call = new ToolCall($this->context);
        $call->status = ToolCallStatus::pending;
        $round = new MessageRound(new Message($this->context), new ArrayClass([$call]));
        $this->assertSame(1, $round->pendingToolCallCount);
        $this->assertSame(0, $round->failedToolCallCount);

        $call->status = ToolCallStatus::error;
        $this->assertSame(0, $round->pendingToolCallCount);
        $this->assertSame(1, $round->failedToolCallCount);

        $call->status = ToolCallStatus::completed;
        $this->assertSame(0, $round->pendingToolCallCount);
        $this->assertSame(0, $round->failedToolCallCount);
    }

    #[Test]
    public function chatTemplateCompilesWithPendingToolStates(): void
    {
        $compiled = new Engine()->compile(dirname(__DIR__, 2) . "/Resources/Views/EditorChat.latte");
        $this->assertStringContainsString("Not completed", $compiled);
        $this->assertStringContainsString("pendingToolCallCount", $compiled);
    }
}
