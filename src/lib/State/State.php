<?php

namespace Dapr\State;

use Dapr\consistency\Consistency;
use Dapr\consistency\EventualLastWrite;
use Dapr\DaprClient;
use Dapr\exceptions\NoStorage;
use Dapr\exceptions\SaveStateFailure;
use Dapr\Serializer;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base class for handling state.
 * @package Dapr
 * @see https://v1-rc1.docs.dapr.io/reference/api/state_api/
 */
class State
{
    /**
     * @var Consistency
     */
    private Consistency $consistency;

    /**
     * Create an object for holding state.
     *
     * @param string $store_name The store to connect to.
     * @param Consistency|null $consistency
     * @param string $key_prepend
     */
    #[Pure]
    public function __construct(
        private string $store_name,
        ?Consistency $consistency = null,
        private string $key_prepend = ''
    ) {
        $this->consistency = $consistency ?? new EventualLastWrite();
    }

    /**
     * Get a single state value.
     *
     * @param string $store_name The store name to connect to.
     * @param string $key The key to retrieve from the store.
     * @param string $consistency The type of consistency to use.
     * @param array $meta Any metadata to send to the request.
     *
     * @return State A configured state object with the key set.
     */
    public static function get_single(
        string $store_name,
        string $key,
        ?string $consistency = null,
        array $meta = []
    ): State {
        $result = DaprClient::get(
            DaprClient::get_api("/state/$store_name/$key", array_merge(['consistency' => $consistency], $meta))
        );

        // todo: add etag
        switch ($result->code) {
            case 200:
                $state       = new State($store_name);
                $state->$key = $result->data;

                return $state;
            case 204:
                $state       = new State($store_name);
                $state->$key = null;

                return $state;
            case 400:
            case 500:
            default:
                throw new NoStorage("State store is missing or misconfigured");
        }
    }

    /**
     * Saves state to the store.
     */
    public function save_state(): void
    {
        $data  = $this->get_keys();
        $state = [];
        foreach ($data as $key) {
            $value = $this->$key;

            $item = [
                'key'   => $key,
                'value' => Serializer::as_json($value),
            ];

            $meta_key    = "${key}__meta";
            $etag_key    = "${key}__etag";
            $options_key = "${key}__options";

            if (isset($this->$meta_key)) {
                $item['metadata'] = $this->$meta_key;
            }

            if (isset($this->$etag_key)) {
                $item['etag'] = $this->$etag_key;
            }

            if (isset($this->$options_key)) {
                $item['options'] = $this->$options_key;
            }

            $state[] = $item;
        }

        $result = DaprClient::post(DaprClient::get_api("/state/{$this->store_name}"), $state);

        switch ($result->code) {
            case 201:
            case 204:
                return;
            case 400:
                throw new NoStorage("State store is missing or misconfigured or malformed request");
            case 500:
            default:
                throw new SaveStateFailure("Failed to save state");
        }
    }

    /**
     * Get available keys defined on the object.
     * @return array The keys on the object.
     */
    private function get_keys(): array
    {
        $keys = new ReflectionClass($this);

        $unregistered_keys = array_filter(
            array_keys((array)$this),
            function ($key) {
                return ! $this->is_special_key($key) && ctype_print($key);
            }
        );

        return array_values(
            array_unique(
                array_merge(
                    $unregistered_keys,
                    array_map(
                        function (ReflectionProperty $item) {
                            return $item->getName();
                        },
                        $keys->getProperties(ReflectionProperty::IS_PUBLIC)
                    )
                )
            )
        );
    }

    private function is_special_key($key)
    {
        switch (true) {
            case $key === 'store_name':
            case strpos($key, '__meta') > 0:
            case strpos($key, '__etag') > 0:
            case strpos($key, '__options') > 0:
                return true;
            default:
                return false;
        }
    }

    /**
     * Load state from the store.
     *
     * @param array|null $metadata Metadata to send to the store.
     *
     * @throws NoStorage
     * @throws SaveStateFailure
     */
    public function load(?array $metadata = null): void
    {
        $keys = $this->prepend_keys($this->get_keys());

        $result = DaprClient::post(
            DaprClient::get_api("/state/{$this->store_name}/bulk", $metadata),
            [
                'keys'        => $keys,
                'parallelism' => 10, //todo: make configurable
            ]
        );

        switch ($result->code) {
            case 200:
                if ( ! is_array($result->data)) {
                    return;
                }
                foreach ($result->data as $value) {
                    $key         = trim($value['key']);
                    $key         = str_replace($this->key_prepend, '', $key);
                    $data        = $value['data'] ?? null;
                    $etag        = $value['etag'] ?? null;
                    $etag_key    = "${key}__etag";
                    $options_key = "${key}__options";
                    // only set the state if the etag set, otherwise, use the class's default value
                    if ($key !== null && $etag !== null) {
                        $this->$key      = $data;
                        $this->$etag_key = $etag;
                    } elseif ($key !== null) {
                        $this->$etag_key = $etag;
                    }
                    $this->$options_key = [
                        'consistency' => $this->consistency->get_consistency(),
                        'concurrency' => $this->consistency->get_concurrency(),
                    ];
                }

                return;
            case 400:
                throw new NoStorage("State store is missing or misconfigured");
            case 500:
            default:
                throw new SaveStateFailure("Get bulk state failed");
        }
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    private function prepend_keys(array $keys): array
    {
        return array_map(
            function ($key) {
                return $this->key_prepend.$key;
            },
            $keys
        );
    }
}
