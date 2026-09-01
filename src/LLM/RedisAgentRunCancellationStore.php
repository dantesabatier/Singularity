<?php

declare(strict_types=1);

namespace App\LLM;

use Override;
use Redis;

/** Redis-backed cancellation signals shared by concurrent PHP requests. */
final readonly class RedisAgentRunCancellationStore implements AgentRunCancellationStore
{
    private const int timeToLive = 600;
    private Redis $redis;

    /**
     * @param string $host The Redis server hostname.
     * @param int $port The Redis server port.
     */
    public function __construct(string $host = "127.0.0.1", int $port = 6379)
    {
        $this->redis = new Redis();
        $this->redis->pconnect($host, $port);
    }

    #[Override]
    public function requestCancellation(string $runIdentifier): void
    {
        $this->redis->setex($this->key($runIdentifier), self::timeToLive, "1");
    }

    #[Override]
    public function consumeCancellation(string $runIdentifier): bool
    {
        $key = $this->key($runIdentifier);
        if ($this->redis->get($key) !== "1") {
            return false;
        }
        $this->redis->del($key);
        return true;
    }

    #[Override]
    public function clear(string $runIdentifier): void
    {
        $this->redis->del($this->key($runIdentifier));
    }

    private function key(string $runIdentifier): string
    {
        return "singularity:llm-cancellation:" . hash("sha256", $runIdentifier);
    }
}
