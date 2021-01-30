<?php

namespace Dapr\State;

use Dapr\consistency\Consistency;

interface IManageState
{
    /**
     * Save state to a state store
     *
     * @param string $store_name The store name
     * @param StateItem $item
     */
    public function save_state(
        string $store_name,
        StateItem $item
    ): void;

    /**
     * Retrieve a value from the store
     *
     * @param string $store_name The store name
     * @param string $key The key
     * @param mixed $default_value The default value if not set
     * @param array $metadata Metadata to pass on to the store
     * @param Consistency|null $consistency Whether to use strong or eventual consistency
     *
     * @return mixed The stored value
     */
    public function load_state(string $store_name, string $key, mixed $default_value = null, array $metadata = [], ?Consistency $consistency = null): mixed;

    /**
     * Delete state keys
     *
     * @param string $store_name The store name
     * @param array $metadata Metadata to pass on to the store
     * @param string ...$keys The keys to delete
     */
    public function delete_keys(string $store_name, array $keys, array $metadata = []): void;

    /**
     * Saves an object where each property is a key in the store.
     *
     * @param object $item The item to store as keys
     * @param string $prefix Prefix keys with this string
     * @param array|null $metadata Metadata
     */
    public function save_object(
        object $item,
        string $prefix = '',
        array|null $metadata = null
    ): void;

    /**
     * Load keys into an object
     *
     * @param object $into The object to load keys into
     * @param string $prefix The prefix of the keys
     * @param int $parallelism The amount of keys to load at one time
     * @param array $metadata Metadata to pass to the store
     *
     * @return void The loaded object
     */
    public function load_object(object $into, string $prefix = '', int $parallelism = 10, array $metadata = []): void;
}
