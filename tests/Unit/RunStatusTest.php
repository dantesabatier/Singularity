<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\LLM\RunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRun;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;

final class RunStatusTest extends TestCase
{
    #[Test]
    #[DataProvider("endings")]
    public function everyEndingPreservesReasonAndRetryability(LLMRunStopReason $reason, ?bool $retryable): void
    {
        $run = new LLMRun(new ArrayClass([new LLMMessage(LLMMessageRole::assistant, "Answer")]), stopReason: $reason, isRetryable: $retryable);
        $status = new RunStatus($run)->jsonSerialize();

        $this->assertSame($reason->value, $status["stopReason"]);
        $this->assertSame($retryable, $status["isRetryable"]);
        $this->assertSame($reason === LLMRunStopReason::done, $status["isComplete"]);
        if ($status["isComplete"]) {
            $this->assertNull($status["message"]);
        } else {
            $this->assertNotEmpty($status["message"]);
        }
    }

    /** @return iterable<string, array{LLMRunStopReason, ?bool}> */
    public static function endings(): iterable
    {
        foreach (LLMRunStopReason::cases() as $reason) {
            foreach ([null, false, true] as $retryable) {
                yield $reason->value . "-" . var_export($retryable, true) => [$reason, $retryable];
            }
        }
    }

    #[Test]
    public function emptyOrUnansweredDoneRunsAreStillIncomplete(): void
    {
        $call = new LLMToolCall("pending", "update", new Dictionary());
        foreach ([new ArrayClass(), new ArrayClass([new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([$call]))])] as $messages) {
            $status = new RunStatus(new LLMRun($messages))->jsonSerialize();
            $this->assertSame("done", $status["stopReason"]);
            $this->assertFalse($status["isComplete"]);
            $this->assertNotEmpty($status["message"]);
        }
    }

    #[Test]
    public function wireEnvelopePreservesNullAndNeverIncludesModelContent(): void
    {
        $status = new RunStatus(new LLMRun(new ArrayClass([new LLMMessage(LLMMessageRole::assistant, "Private content")])));
        $wire = json_encode(new Dictionary(["run" => $status]), JSON_THROW_ON_ERROR);
        $this->assertSame("{\"run\":{\"stopReason\":\"done\",\"isComplete\":true,\"isRetryable\":null,\"message\":null}}", $wire);
    }
}
