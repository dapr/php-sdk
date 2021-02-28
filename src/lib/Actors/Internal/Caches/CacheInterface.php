<?php

namespace Dapr\Actors\Internal\Caches;

/**
 * Interface CacheInterface
 * @package Dapr\Actors\Internal\Caches
 */
interface CacheInterface
{
    /**
     * CacheInterface constructor.
     *
     * @param string $cache_name The name of the cache
     */
    public function __construct(string $cache_name);

    /**
     * Retrieve a key from the cache
     *
     * @param string $key The key
     *
     * @return mixed The stored value
     */
    public function get_key(string $key): mixed;

    /**
     * Set a key's value in the cache
     *
     * @param string $key The key
     * @param mixed $data The cached value
     */
    public function set_key(string $key, mixed $data): void;

    /**
     * Evict a key from the cache
     *
     * @param string $key The key
     */
    public function evict(string $key): void;

    /**
     * Delete the entire cache
     */
    public function reset(): void;
}
