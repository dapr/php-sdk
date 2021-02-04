<?php

namespace Dapr\Deserialization;

use Dapr\Deserialization\Deserializers\DateInterval;
use Dapr\Deserialization\Deserializers\DateTime;
use Dapr\Deserialization\Deserializers\IDeserialize;

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

    public function add(string $type, IDeserialize $deserializer)
    {
        $this->deserializers[$type] = $deserializer;
    }
}
