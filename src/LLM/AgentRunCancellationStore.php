<?php

declare(strict_types=1);

namespace App\LLM;

/** Shared cancellation signal used by the chat request and its cancellation endpoint. */
interface AgentRunCancellationStore
{
    /**
     * Records a cancellation request for an active run.
     * @param string $runIdentifier The run to cancel.
     */
    public function requestCancellation(string $runIdentifier): void;

    /**
     * Consumes any pending cancellation request for a run.
     * @param string $runIdentifier The run whose cancellation signal is consumed.
     */
    public function consumeCancellation(string $runIdentifier): bool;

    /**
     * Removes any pending cancellation request for a run.
     * @param string $runIdentifier The run whose cancellation signal is cleared.
     */
    public function clear(string $runIdentifier): void;
}
