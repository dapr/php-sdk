<?php

namespace Dapr\Deserialization;

use Dapr\Deserialization\Deserializers\IDeserialize;

class DeserializationConfig
{
    public function __construct(protected array $deserializers = [])
    {
    }

    public function add(string $type, IDeserialize $deserializer)
    {
        $this->deserializers[$type] = $deserializer;
    }
}
