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
     * @param string $dapr_type The dapr type
     * @param string $actor_id The actor id
     * @param string $state_name The name of the state type
     */
    public function __construct(string $dapr_type, string $actor_id, string $state_name);

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

    /**
     * Write to the cache
     */
    public function flush_cache(): void;

    /**
     * Determine if a key is in the cache
     *
     * @param string $key The key to check
     *
     * @return bool True if the item is in the cache
     */
    public function has_key(string $key): bool;
}
