<?php

declare(strict_types=1);

namespace App\LLM;

use Override;
use Sabatier\Service\LLM\LLMRunEvent;
use Sabatier\Service\LLM\LLMRunInterruptedException;
use Sabatier\Service\LLM\LLMRunObserver;
use Sabatier\Service\LLM\LLMRunStopReason;

/** Stops an agent at the next observed runtime boundary after cancellation is requested. */
final class AgentRunCancellationObserver implements LLMRunObserver
{
    /** @var bool Whether cancellation interrupted the observed run. */
    public private(set) bool $wasCancelled = false;

    /**
     * @param string $runIdentifier The identifier shared with the cancellation endpoint.
     * @param AgentRunCancellationStore $store The store carrying cancellation requests between HTTP requests.
     */
    public function __construct(private readonly string $runIdentifier, private readonly AgentRunCancellationStore $store)
    {
    }

    #[Override]
    public function observe(LLMRunEvent $event): void
    {
        if ($this->wasCancelled || !$this->store->consumeCancellation($this->runIdentifier)) {
            return;
        }
        $this->wasCancelled = true;
        throw new LLMRunInterruptedException(LLMRunStopReason::deadline, false);
    }
}
