<?php

declare(strict_types=1);

namespace App\LLM;

use JsonSerializable;
use Override;
use Sabatier\Service\LLM\LLMRun;
use Sabatier\Service\LLM\LLMRunStopReason;

/** Run status for the UI, separate from the history sent to the model. */
final class RunStatus implements JsonSerializable
{
    private LLMRun $run;

    /**
     * @param LLMRun $run The run whose outcome is reported.
     */
    public function __construct(LLMRun $run)
    {
        $this->run = $run;
    }

    /**
     * @return array{stopReason: string, isComplete: bool, isRetryable: ?bool, message: ?string}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            "stopReason" => $this->run->stopReason->value,
            "isComplete" => $this->run->isComplete,
            "isRetryable" => $this->run->isRetryable,
            "message" => $this->run->isComplete ? null : match ($this->run->stopReason) {
                LLMRunStopReason::done => "The run ended without a complete answer.",
                LLMRunStopReason::iterationCap => "The iteration limit was reached.",
                LLMRunStopReason::deadline => "The run time limit was reached.",
                LLMRunStopReason::providerFailure => "The model provider could not complete the request.",
                LLMRunStopReason::toolProviderFailure => "The tool provider could not complete the request.",
                LLMRunStopReason::outputLimit => "The model reached its output limit.",
                LLMRunStopReason::refusal => "The model declined the request.",
                LLMRunStopReason::toolCallLimit => "The tool call limit was reached.",
                LLMRunStopReason::subagentCallLimit => "The subagent call limit was reached.",
                LLMRunStopReason::inputTokenLimit => "The input token budget was exhausted.",
                LLMRunStopReason::outputTokenLimit => "The output token budget was exhausted.",
                LLMRunStopReason::totalTokenLimit => "The total token budget was exhausted.",
                LLMRunStopReason::writeApprovalRequired => "A tool requires write approval; that call was not executed.",
                LLMRunStopReason::contextLimit => "The context exceeds the configured limit.",
            },
        ];
    }
}
