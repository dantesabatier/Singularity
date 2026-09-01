<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\LLM\AgentRunCancellationObserver;
use App\LLM\AgentRunCancellationStore;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Set;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRunObserverFailurePolicy;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class AgentRunCancellationObserverTest extends TestCase
{
    #[Test]
    public function requestedCancellationStopsBeforeTheNextProviderBoundary(): void
    {
        $store = new InMemoryAgentRunCancellationStore();
        $store->requestCancellation("run-1");
        $observer = new AgentRunCancellationObserver("run-1", $store);
        $client = new CancellationTestClient();
        $agent = new LLMAgent(
            $client,
            new ToolRegistry(new ArrayClass()),
            observer: $observer,
            observerFailurePolicy: LLMRunObserverFailurePolicy::strict,
        );

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "Stop")]));

        $this->assertSame(LLMRunStopReason::deadline, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertFalse($run->isRetryable);
        $this->assertTrue($run->messages->isEmpty);
        $this->assertSame(0, $client->callCount);
        $this->assertTrue($observer->wasCancelled);
        $this->assertFalse($store->consumeCancellation("run-1"));
    }
}

final class InMemoryAgentRunCancellationStore implements AgentRunCancellationStore
{
    /** @var Set<string> */
    private Set $cancellations;

    public function __construct()
    {
        $this->cancellations = new Set();
    }

    #[Override]
    public function requestCancellation(string $runIdentifier): void
    {
        $this->cancellations->insert($runIdentifier);
    }

    #[Override]
    public function consumeCancellation(string $runIdentifier): bool
    {
        if (!$this->cancellations->containsElement($runIdentifier)) {
            return false;
        }
        $this->cancellations->remove($runIdentifier);
        return true;
    }

    #[Override]
    public function clear(string $runIdentifier): void
    {
        $this->cancellations->remove($runIdentifier);
    }
}

final class CancellationTestClient extends LLMClient
{
    public string $version = "";
    public int $maxTokens = 1;
    public int $callCount = 0;

    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        $this->callCount++;
        return new LLMTurn("Unexpected", new ArrayClass());
    }

    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Not used");
    }

    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Not used");
    }
}
