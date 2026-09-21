<?php

namespace Dapr\Configuration;

/**
 * Class ConfigurationItem
 *
 * A single configuration item as returned by a configuration store.
 *
 * Note: properties use plain defaults (rather than constructor promotion) because the
 * deserializer instantiates this class without calling the constructor, and stores may
 * omit keys such as version or metadata.
 *
 * @package Dapr\Configuration
 */
class ConfigurationItem
{
    /**
     * @var string The value of the configuration item
     */
    public string $value = '';

    /**
     * @var string The version of the configuration item, if the store supports versions
     */
    public string $version = '';

    /**
     * @var array|null Optional metadata associated with the configuration item
     */
    public array|null $metadata = null;
}
