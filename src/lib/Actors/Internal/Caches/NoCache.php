<?php

namespace Dapr\Actors\Internal\Caches;

/**
 * Class NoCache
 * @package Dapr\Actors\Internal\Caches
 */
class NoCache implements CacheInterface
{
    /**
     * @inheritDoc
     */
    public function __construct(private string $cache_name)
    {
    }

    /**
     * @inheritDoc
     */
    public function get_key(string $key): mixed
    {
        throw new KeyNotFound();
    }

    /**
     * @inheritDoc
     */
    public function set_key(string $key, mixed $data): void
    {
    }

    /**
     * @inheritDoc
     */
    public function evict(string $key): void
    {
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
    }
}
