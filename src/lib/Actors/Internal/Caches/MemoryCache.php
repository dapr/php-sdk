<?php

namespace Dapr\Actors\Internal\Caches;

use Dapr\Actors\ActorReference;

/**
 * Class NoCache
 * @package Dapr\Actors\Internal\Caches
 */
class MemoryCache implements CacheInterface
{
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function __construct(ActorReference $reference, string $state_name)
    {
    }

    /**
     * @inheritDoc
     */
    public function get_key(string $key): mixed
    {
        if ($this->has_key($key)) {
            return $this->data[$key];
        }
        throw new KeyNotFound();
    }

    /**
     * @inheritDoc
     */
    public function set_key(string $key, mixed $data): void
    {
        $this->data[$key] = $data;
    }

    /**
     * @inheritDoc
     */
    public function evict(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * @inheritDoc
     */
    public function flush_cache(): void
    {
    }

    public function has_key(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
