<?php

namespace Dapr\Configuration;

/**
 * Class ConfigurationUpdate
 *
 * Represents a configuration change notification pushed by the sidecar to the
 * application's `/configuration/<store-name>` route after subscribing via
 * DaprClient::subscribeConfiguration().
 *
 * @package Dapr\Configuration
 */
class ConfigurationUpdate
{
    /**
     * ConfigurationUpdate constructor.
     *
     * @param string $id The subscription id this update belongs to
     * @param ConfigurationItem[] $items The updated configuration items, keyed by their configuration key
     */
    public function __construct(
        public string $id = '',
        public array $items = []
    ) {
    }

    /**
     * Parse the raw JSON body of a configuration update delivered by the sidecar.
     *
     * @param string $json The raw JSON body
     *
     * @return ConfigurationUpdate The parsed update
     */
    public static function parse(string $json): ConfigurationUpdate
    {
        $raw = json_decode($json, true) ?? [];
        $update = new ConfigurationUpdate();
        $update->id = $raw['id'] ?? '';
        $update->items = array_map(
            function (array $item) {
                $configItem = new ConfigurationItem();
                $configItem->value = $item['value'] ?? '';
                $configItem->version = $item['version'] ?? '';
                $configItem->metadata = $item['metadata'] ?? null;

                return $configItem;
            },
            $raw['items'] ?? []
        );

        return $update;
    }
}
