<?php

namespace Dapr\Deserialization;

use Dapr\Deserialization\Deserializers\DateInterval;
use Dapr\Deserialization\Deserializers\DateTime;
use Dapr\Deserialization\Deserializers\IDeserialize;

/**
 * Class DeserializationConfig
 *
 * Handles default deserialization
 *
 * @package Dapr\Deserialization
 */
class DeserializationConfig
{
    /**
     * DeserializationConfig constructor.
     *
     * @param IDeserialize[] $deserializers
     */
    public function __construct(protected array $deserializers = [])
    {
        if (empty($this->deserializers[\DateInterval::class])) {
            $this->add(\DateInterval::class, new DateInterval());
        }
        if (empty($this->deserializers[\DateTime::class])) {
            $this->add(\DateTime::class, new DateTime());
        }
    }

    /**
     * Adds a deserializer for a given type
     *
     * @param string $type
     * @param IDeserialize $deserializer
     */
    public function add(string $type, IDeserialize $deserializer): void
    {
        $this->deserializers[$type] = $deserializer;
    }
}
